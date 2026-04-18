<?php defined( 'ABSPATH' ) || exit; ?>

<!-- ============================================================
     BPD  –  Trigger button (injected via JS into the page header)
     ============================================================ -->
<button id="bpd-open-btn" class="page-title-action bpd-trigger-btn" type="button">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
         stroke-linejoin="round" aria-hidden="true">
        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
        <path d="M10 11v6"/><path d="M14 11v6"/>
        <path d="M9 6V4h6v2"/>
    </svg>
    حذف منتجات عبر Excel
</button>

<!-- ============================================================
     BPD  –  Modal overlay
     ============================================================ -->
<div id="bpd-overlay" class="bpd-overlay" role="dialog"
     aria-modal="true" aria-labelledby="bpd-modal-title" hidden>

    <div class="bpd-modal">

        <!-- Header -->
        <div class="bpd-modal__header">
            <div class="bpd-modal__icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                     stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6l-1 14H6L5 6"/>
                    <path d="M10 11v6"/><path d="M14 11v6"/>
                    <path d="M9 6V4h6v2"/>
                </svg>
            </div>
            <div>
                <h2 id="bpd-modal-title" class="bpd-modal__title">حذف منتجات عبر Excel</h2>
                <p class="bpd-modal__subtitle">ارفع ملف Excel يحتوي على أسماء المنتجات أو رموز SKU</p>
            </div>
            <button id="bpd-close-btn" class="bpd-modal__close" aria-label="إغلاق">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <!-- Body -->
        <div class="bpd-modal__body">

            <!-- Step 1: Upload -->
            <div id="bpd-step-upload" class="bpd-step">

                <!-- Match mode -->
                <div class="bpd-field">
                    <label class="bpd-label">طريقة المطابقة</label>
                    <div class="bpd-radio-group">
                        <label class="bpd-radio">
                            <input type="radio" name="bpd_mode" value="sku" checked>
                            <span class="bpd-radio__box"></span>
                            <span>رمز SKU</span>
                        </label>
                        <label class="bpd-radio">
                            <input type="radio" name="bpd_mode" value="name">
                            <span class="bpd-radio__box"></span>
                            <span>اسم المنتج</span>
                        </label>
                    </div>
                </div>

                <!-- Column index -->
                <div class="bpd-field">
                    <label class="bpd-label" for="bpd-col-index">
                        رقم العمود في Excel
                        <span class="bpd-hint">(العمود الأول = 1)</span>
                    </label>
                    <input id="bpd-col-index" class="bpd-input" type="number"
                           value="1" min="1" max="100">
                </div>

                <!-- File drop zone -->
                <div class="bpd-field">
                    <label class="bpd-label">ملف Excel</label>
                    <div id="bpd-dropzone" class="bpd-dropzone">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                             stroke-linejoin="round" class="bpd-dropzone__icon">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <p class="bpd-dropzone__text">اسحب ملف Excel هنا أو</p>
                        <label class="bpd-btn bpd-btn--secondary bpd-dropzone__browse" for="bpd-file-input">
                            تصفح الملفات
                        </label>
                        <p class="bpd-dropzone__hint">.xlsx أو .xls — حتى 10 ميغابايت</p>
                        <input id="bpd-file-input" type="file" accept=".xlsx,.xls" hidden>
                    </div>
                    <div id="bpd-file-selected" class="bpd-file-selected" hidden>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <span id="bpd-file-name"></span>
                        <button id="bpd-file-clear" class="bpd-file-clear" type="button"
                                aria-label="إزالة الملف">✕</button>
                    </div>
                </div>

                <button id="bpd-analyze-btn" class="bpd-btn bpd-btn--primary bpd-btn--full" type="button">
                    تحليل الملف
                </button>
            </div>

            <!-- Step 2: Preview & confirm -->
            <div id="bpd-step-preview" class="bpd-step" hidden>

                <div class="bpd-summary">
                    <div class="bpd-summary__stat">
                        <span id="bpd-found-count" class="bpd-summary__num">0</span>
                        <span class="bpd-summary__label">منتج موجود</span>
                    </div>
                    <div class="bpd-summary__stat bpd-summary__stat--warn">
                        <span id="bpd-notfound-count" class="bpd-summary__num">0</span>
                        <span class="bpd-summary__label">غير موجود</span>
                    </div>
                </div>

                <!-- Products table preview -->
                <div class="bpd-table-wrap">
                    <table class="bpd-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم المنتج</th>
                                <th>SKU</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody id="bpd-preview-body"></tbody>
                    </table>
                </div>

                <!-- Not-found list (collapsed) -->
                <details id="bpd-notfound-details" class="bpd-notfound" hidden>
                    <summary>عرض القيم غير الموجودة</summary>
                    <ul id="bpd-notfound-list" class="bpd-notfound__list"></ul>
                </details>

                <div class="bpd-modal__actions">
                    <button id="bpd-back-btn" class="bpd-btn bpd-btn--ghost" type="button">
                        رجوع
                    </button>
                    <button id="bpd-delete-btn" class="bpd-btn bpd-btn--danger" type="button">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6l-1 14H6L5 6"/>
                            <path d="M10 11v6"/><path d="M14 11v6"/>
                        </svg>
                        حذف المنتجات المحددة
                    </button>
                </div>
            </div>

            <!-- Step 3: Result -->
            <div id="bpd-step-result" class="bpd-step" hidden>
                <div id="bpd-result-content" class="bpd-result"></div>
                <button id="bpd-restart-btn" class="bpd-btn bpd-btn--primary bpd-btn--full" type="button">
                    حذف دفعة جديدة
                </button>
            </div>

            <!-- Spinner overlay -->
            <div id="bpd-spinner" class="bpd-spinner-wrap" hidden>
                <div class="bpd-spinner"></div>
                <p id="bpd-spinner-msg" class="bpd-spinner__msg">جارٍ المعالجة…</p>
            </div>
        </div>
    </div>
</div>
