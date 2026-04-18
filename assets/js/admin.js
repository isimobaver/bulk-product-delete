/* =============================================================
   Bulk Product Delete  –  Admin JS
   ============================================================= */
/* global BPD, jQuery */

(function ($) {
    'use strict';

    /* ── Selectors ─────────────────────────────────────────────── */
    const sel = {
        overlay:       '#bpd-overlay',
        openBtn:       '#bpd-open-btn',
        closeBtn:      '#bpd-close-btn',
        dropzone:      '#bpd-dropzone',
        fileInput:     '#bpd-file-input',
        fileSelected:  '#bpd-file-selected',
        fileName:      '#bpd-file-name',
        fileClear:     '#bpd-file-clear',
        colIndex:      '#bpd-col-index',
        analyzeBtn:    '#bpd-analyze-btn',
        deleteBtn:     '#bpd-delete-btn',
        backBtn:       '#bpd-back-btn',
        restartBtn:    '#bpd-restart-btn',
        stepUpload:    '#bpd-step-upload',
        stepPreview:   '#bpd-step-preview',
        stepResult:    '#bpd-step-result',
        spinner:       '#bpd-spinner',
        spinnerMsg:    '#bpd-spinner-msg',
        previewBody:   '#bpd-preview-body',
        foundCount:    '#bpd-found-count',
        notFoundCount: '#bpd-notfound-count',
        notFoundDet:   '#bpd-notfound-details',
        notFoundList:  '#bpd-notfound-list',
        resultContent: '#bpd-result-content',
        modeRadios:    'input[name="bpd_mode"]',
    };

    /* ── State ──────────────────────────────────────────────────── */
    let selectedFile   = null;
    let foundProducts  = [];   // [{id, name, sku}, …]

    /* ── Helpers ────────────────────────────────────────────────── */
    const show    = (el) => $(el).removeAttr('hidden');
    const hide    = (el) => $(el).attr('hidden', true);
    const spinner = (msg) => { $(sel.spinnerMsg).text(msg); show(sel.spinner); };
    const hideSpinner = () => hide(sel.spinner);

    function showStep(step) {
        hide(sel.stepUpload);
        hide(sel.stepPreview);
        hide(sel.stepResult);
        show(step);
    }

    

    /* ── File selection ─────────────────────────────────────────── */
    function setFile(file) {
        if (!file) return;
        const allowed = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                         'application/vnd.ms-excel'];
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['xlsx', 'xls'].includes(ext)) {
            alert(BPD.i18n.invalid_type);
            return;
        }
        selectedFile = file;
        $(sel.fileName).text(file.name);
        show(sel.fileSelected);
        $(sel.dropzone).hide();
    }

    function clearFile() {
        selectedFile = null;
        $(sel.fileInput).val('');
        $(sel.fileName).text('');
        hide(sel.fileSelected);
        $(sel.dropzone).show();
    }

    /* ── Open / close modal ─────────────────────────────────────── */
    function openModal() {
        show(sel.overlay);
        $(sel.overlay).focus();
        $('body').css('overflow', 'hidden');
    }

    function closeModal() {
        hide(sel.overlay);
        $('body').css('overflow', '');
        resetModal();
    }

    function resetModal() {
        clearFile();
        foundProducts = [];
        showStep(sel.stepUpload);
        hideSpinner();
        $(sel.previewBody).empty();
        $(sel.notFoundList).empty();
        hide(sel.notFoundDet);
    }

    /* ── Analyze (upload + parse) ───────────────────────────────── */
    function analyze() {
        if (!selectedFile) { alert(BPD.i18n.no_file); return; }

        const mode = $(sel.modeRadios + ':checked').val() || 'sku';
        const col  = parseInt($(sel.colIndex).val(), 10) || 1;

        const fd = new FormData();
        fd.append('action',     'bpd_analyze');
        fd.append('nonce',      BPD.nonce);
        fd.append('excel_file', selectedFile);
        fd.append('mode',       mode);
        fd.append('col_index',  col);

        spinner(BPD.i18n.uploading);

        $.ajax({
            url:         BPD.ajaxurl,
            type:        'POST',
            data:        fd,
            processData: false,
            contentType: false,
        })
        .done(function (res) {
            hideSpinner();
            if (!res.success) {
                alert(res.data.message || BPD.i18n.error);
                return;
            }
            foundProducts = res.data.found || [];
            renderPreview(res.data.found, res.data.not_found || []);
        })
        .fail(function () {
            hideSpinner();
            alert(BPD.i18n.error);
        });
    }

    /* ── Render preview table ───────────────────────────────────── */
    function renderPreview(found, notFound) {
        $(sel.foundCount).text(found.length);
        $(sel.notFoundCount).text(notFound.length);

        const $tbody = $(sel.previewBody).empty();
        if (found.length === 0) {
            $tbody.append('<tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:20px">لا توجد منتجات مطابقة</td></tr>');
        } else {
            found.forEach(function (p, i) {
                $tbody.append(
                    `<tr>
                        <td>${i + 1}</td>
                        <td>${escHtml(p.name)}</td>
                        <td><code>${escHtml(p.sku)}</code></td>
                        <td><span class="bpd-badge bpd-badge--found">موجود</span></td>
                    </tr>`
                );
            });
        }

        // Not-found list
        const $nfl = $(sel.notFoundList).empty();
        if (notFound.length > 0) {
            notFound.forEach(v => $nfl.append(`<li>${escHtml(v)}</li>`));
            show(sel.notFoundDet);
        } else {
            hide(sel.notFoundDet);
        }

        // Disable delete button if nothing found
        $(sel.deleteBtn).prop('disabled', found.length === 0);

        showStep(sel.stepPreview);
    }

    /* ── Delete ─────────────────────────────────────────────────── */
    function deleteProducts() {
        if (foundProducts.length === 0) return;

        const confirmMsg = BPD.i18n.confirm.replace('{count}', foundProducts.length);
        if (!confirm(confirmMsg)) return;

        const ids = foundProducts.map(p => p.id);

        spinner(BPD.i18n.deleting);

        $.post(BPD.ajaxurl, {
            action:      'bpd_delete',
            nonce:       BPD.nonce,
            product_ids: ids,
        })
        .done(function (res) {
            hideSpinner();
            const ok = res.success;
            const msg = (res.data && res.data.message) ? res.data.message : (ok ? BPD.i18n.done : BPD.i18n.error);
            $(sel.resultContent)
                .text(msg)
                .toggleClass('is-error', !ok);
            showStep(sel.stepResult);

            if (ok) {
                // Reload product list in background after short delay
                setTimeout(() => window.location.reload(), 2200);
            }
        })
        .fail(function () {
            hideSpinner();
            $(sel.resultContent).text(BPD.i18n.error).addClass('is-error');
            showStep(sel.stepResult);
        });
    }

    /* ── Escape HTML ────────────────────────────────────────────── */
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ── Event bindings ─────────────────────────────────────────── */
    $(document).ready(function () {

        

        // Open
        $(document).on('click', sel.openBtn, openModal);

        // Close
        $(document).on('click', sel.closeBtn, closeModal);
        $(document).on('click', sel.overlay, function (e) {
            if ($(e.target).is(sel.overlay)) closeModal();
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });

        // File input
        $(document).on('change', sel.fileInput, function () {
            setFile(this.files[0]);
        });

        $(document).on('click', sel.fileClear, clearFile);

        // Drag & drop
        $(document).on('dragover dragenter', sel.dropzone, function (e) {
            e.preventDefault();
            $(this).addClass('drag-over');
        });
        $(document).on('dragleave drop', sel.dropzone, function (e) {
            $(this).removeClass('drag-over');
        });
        $(document).on('drop', sel.dropzone, function (e) {
            e.preventDefault();
            const file = e.originalEvent.dataTransfer.files[0];
            setFile(file);
        });

        // Click on dropzone (but not on browse label) opens file picker
        $(document).on('click', sel.dropzone, function (e) {
            if (!$(e.target).closest('.bpd-dropzone__browse').length) {
                $(sel.fileInput).trigger('click');
            }
        });

        // Analyze
        $(document).on('click', sel.analyzeBtn, analyze);

        // Delete confirm
        $(document).on('click', sel.deleteBtn, deleteProducts);

        // Back
        $(document).on('click', sel.backBtn, function () {
            showStep(sel.stepUpload);
        });

        // Restart
        $(document).on('click', sel.restartBtn, resetModal);
    });

}(jQuery));
