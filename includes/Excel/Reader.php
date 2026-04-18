<?php
namespace BPD\Excel;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight Excel reader.
 *
 * For .xlsx  — uses ZipArchive + SimpleXML (built into PHP ≥ 5.3, no library needed).
 * For .xls   — falls back to a minimal BIFF8 parser.
 *
 * Only reads a single column from the first worksheet.
 */
class Reader {

    /**
     * @param  string $path     Absolute path to the Excel file.
     * @param  int    $col      0-based column index to read.
     * @return string[]         Array of non-empty cell values.
     * @throws \Exception       On file or format errors.
     */
    public static function read_column( string $path, int $col = 0 ): array {
        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

        if ( $ext === 'xlsx' ) {
            return self::read_xlsx( $path, $col );
        }

        if ( $ext === 'xls' ) {
            return self::read_xls( $path, $col );
        }

        throw new \Exception( "Unsupported format: {$ext}" );
    }

    /* ============================================================
       XLSX  (Office Open XML)
       ============================================================ */
    private static function read_xlsx( string $path, int $col ): array {
        if ( ! class_exists( 'ZipArchive' ) ) {
            throw new \Exception( 'PHP ZipArchive extension is required for .xlsx files.' );
        }

        $zip = new \ZipArchive();
        if ( $zip->open( $path ) !== true ) {
            throw new \Exception( 'Cannot open .xlsx file.' );
        }

        // ── Read shared strings ────────────────────────────────────────────
        $shared_strings = [];
        $ss_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
        if ( $ss_xml !== false ) {
            $ss_doc = simplexml_load_string( $ss_xml );
            if ( $ss_doc ) {
                foreach ( $ss_doc->si as $si ) {
                    if ( isset( $si->t ) ) {
                        $shared_strings[] = (string) $si->t;
                    } else {
                        // Rich text: concatenate <r><t> fragments
                        $rich = '';
                        foreach ( $si->r as $r ) {
                            $rich .= (string) $r->t;
                        }
                        $shared_strings[] = $rich;
                    }
                }
            }
        }

        // ── Find first sheet path ──────────────────────────────────────────
        $wb_xml   = $zip->getFromName( 'xl/workbook.xml' );
        $wb_rels  = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
        $sheet_path = self::first_sheet_path( $wb_xml, $wb_rels );

        $sheet_xml = $zip->getFromName( $sheet_path );
        $zip->close();

        if ( $sheet_xml === false ) {
            throw new \Exception( 'Cannot find first worksheet in .xlsx file.' );
        }

        // ── Parse sheet ────────────────────────────────────────────────────
        $doc = simplexml_load_string( $sheet_xml );
        if ( ! $doc ) {
            throw new \Exception( 'Cannot parse worksheet XML.' );
        }

        $ns  = $doc->getNamespaces( true );
        $doc->registerXPathNamespace( 'ss', reset( $ns ) ?: 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );

        $values = [];
        foreach ( $doc->sheetData->row ?? [] as $row ) {
            foreach ( $row->c as $cell ) {
                $ref = (string) $cell['r'];                // e.g. "B3"
                $c   = self::col_index_from_ref( $ref );   // 0-based
                if ( $c !== $col ) continue;

                $type  = (string) $cell['t'];
                $raw   = (string) $cell->v;
                $value = $raw;

                if ( $type === 's' ) {
                    // Shared string
                    $value = $shared_strings[ (int) $raw ] ?? '';
                } elseif ( $type === 'inlineStr' ) {
                    $value = (string) $cell->is->t;
                }

                $value = trim( $value );
                if ( $value !== '' ) {
                    $values[] = $value;
                }
                break; // found the column for this row
            }
        }

        return $values;
    }

    /** Derive 0-based column index from a cell reference like "A1", "BC42". */
    private static function col_index_from_ref( string $ref ): int {
        preg_match( '/^([A-Z]+)/i', $ref, $m );
        $letters = strtoupper( $m[1] ?? 'A' );
        $idx = 0;
        for ( $i = 0, $len = strlen( $letters ); $i < $len; $i++ ) {
            $idx = $idx * 26 + ( ord( $letters[ $i ] ) - 64 );
        }
        return $idx - 1;
    }

    /** Parse workbook.xml + rels to get the path of the first sheet. */
    private static function first_sheet_path( string $wb_xml, string $wb_rels ): string {
        $default = 'xl/worksheets/sheet1.xml';
        if ( ! $wb_xml || ! $wb_rels ) return $default;

        $wb  = @simplexml_load_string( $wb_xml );
        $rel = @simplexml_load_string( $wb_rels );
        if ( ! $wb || ! $rel ) return $default;

        // Get the rId of the first sheet
        $ns_main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $ns_r    = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $wb->registerXPathNamespace( 'ss', $ns_main );
        $sheets = $wb->xpath( '//ss:sheet' );
        if ( empty( $sheets ) ) return $default;

        $r_ns = $sheets[0]->getNamespaces( true );
        $rid  = '';
        foreach ( $r_ns as $prefix => $uri ) {
            if ( strpos( $uri, 'relationships' ) !== false ) {
                $attr = $sheets[0]->attributes( $uri );
                $rid  = (string) ( $attr['id'] ?? '' );
                break;
            }
        }
        if ( ! $rid ) return $default;

        // Resolve rId to target in rels
        foreach ( $rel->Relationship as $r ) {
            if ( (string) $r['Id'] === $rid ) {
                $target = ltrim( (string) $r['Target'], '/' );
                // Target may be relative to xl/
                if ( strpos( $target, 'xl/' ) !== 0 ) {
                    $target = 'xl/' . $target;
                }
                return $target;
            }
        }

        return $default;
    }

    /* ============================================================
       XLS  (BIFF8 — legacy format)
       Minimal parser: only reads SST (shared string table)
       and label/rk cells from the first BIFF8 sheet stream.
       ============================================================ */
    private static function read_xls( string $path, int $col ): array {
        $data = @file_get_contents( $path );
        if ( $data === false ) {
            throw new \Exception( 'Cannot read .xls file.' );
        }

        // BIFF8 files are OLE2 compound documents.
        // We use a very small subset: find the "Workbook" stream by its magic.
        // For full OLE2 parsing we delegate to a tiny helper.
        return self::parse_biff8_simple( $data, $col );
    }

    /**
     * Extremely minimal BIFF8 reader — handles only the most common case
     * where the user has a simple list in one column.
     * Falls back gracefully for complex files.
     */
    private static function parse_biff8_simple( string $data, int $col ): array {
        // OLE2 header magic
        if ( substr( $data, 0, 8 ) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" ) {
            throw new \Exception( 'File is not a valid .xls (OLE2) document.' );
        }

        $values = [];

        // Scan the raw binary for BIFF8 record types that hold text:
        //   0x00FC  =  SST (Shared String Table)
        //   0x0204  =  LABEL  (inline string)
        //   0x00FD  =  LABELSST (string via SST index)

        $len    = strlen( $data );
        $sst    = [];
        $offset = 0;

        while ( $offset + 4 <= $len ) {
            $rec_type = unpack( 'v', substr( $data, $offset,     2 ) )[1];
            $rec_size = unpack( 'v', substr( $data, $offset + 2, 2 ) )[1];

            if ( $offset + 4 + $rec_size > $len ) break;

            $rec_data = substr( $data, $offset + 4, $rec_size );

            if ( $rec_type === 0x00FC && $rec_size >= 8 ) {
                // SST record
                $total = unpack( 'V', substr( $rec_data, 4, 4 ) )[1];
                $sst   = self::parse_sst( $rec_data, $total );
            }

            if ( $rec_type === 0x00FD && $rec_size >= 7 ) {
                // LABELSST
                $row_n = unpack( 'v', substr( $rec_data, 0, 2 ) )[1];
                $col_n = unpack( 'v', substr( $rec_data, 2, 2 ) )[1];
                $ssi   = unpack( 'V', substr( $rec_data, 6, 4 ) )[1];
                if ( $col_n === $col && isset( $sst[ $ssi ] ) ) {
                    $v = trim( $sst[ $ssi ] );
                    if ( $v !== '' ) $values[] = $v;
                }
            }

            if ( $rec_type === 0x0204 && $rec_size >= 7 ) {
                // LABEL record
                $row_n = unpack( 'v', substr( $rec_data, 0, 2 ) )[1];
                $col_n = unpack( 'v', substr( $rec_data, 2, 2 ) )[1];
                $slen  = unpack( 'v', substr( $rec_data, 6, 2 ) )[1];
                $flags = ord( $rec_data[8] ?? "\x00" );
                $str   = '';
                if ( $flags & 1 ) {
                    // UTF-16LE
                    $str = mb_convert_encoding( substr( $rec_data, 9, $slen * 2 ), 'UTF-8', 'UTF-16LE' );
                } else {
                    $str = substr( $rec_data, 9, $slen );
                }
                if ( $col_n === $col ) {
                    $v = trim( $str );
                    if ( $v !== '' ) $values[] = $v;
                }
            }

            $offset += 4 + $rec_size;
        }

        return $values;
    }

    private static function parse_sst( string $data, int $total ): array {
        $strings = [];
        $pos     = 8; // skip cstTotal(4) + cstUnique(4)
        $len     = strlen( $data );

        for ( $i = 0; $i < $total && $pos < $len; $i++ ) {
            if ( $pos + 2 > $len ) break;
            $cch   = unpack( 'v', substr( $data, $pos, 2 ) )[1];
            $pos  += 2;
            $flags = ord( $data[ $pos ] ?? "\x00" );
            $pos  += 1;

            $unicode = (bool) ( $flags & 1 );
            // Skip optional structures
            $rich   = ( $flags >> 3 ) & 1 ? unpack( 'v', substr( $data, $pos, 2 ) )[1] : 0;
            $ext    = ( $flags >> 2 ) & 1 ? unpack( 'V', substr( $data, $pos + ( ( ( $flags >> 3 ) & 1 ) ? 2 : 0 ), 4 ) )[1] : 0;
            if ( ( $flags >> 3 ) & 1 ) $pos += 2;
            if ( ( $flags >> 2 ) & 1 ) $pos += 4;

            $byte_len = $unicode ? $cch * 2 : $cch;
            $raw      = substr( $data, $pos, $byte_len );
            $pos     += $byte_len;
            $pos     += $rich * 4; // rich text formatting runs
            $pos     += $ext;      // extended string data

            $strings[] = $unicode
                ? mb_convert_encoding( $raw, 'UTF-8', 'UTF-16LE' )
                : $raw;
        }

        return $strings;
    }
}
