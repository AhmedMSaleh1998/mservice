<div id="file-upload-image-preview-modal" hidden>
    <div class="file-upload-image-preview-backdrop" data-preview-backdrop></div>
    <div
        class="file-upload-image-preview-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="file-upload-image-preview-title"
    >
        <div class="file-upload-image-preview-header">
            <h3 id="file-upload-image-preview-title">{{ __('Image Preview') }}</h3>
            <button
                type="button"
                class="file-upload-image-preview-close"
                data-preview-close
                aria-label="{{ __('Close') }}"
            >
                &times;
            </button>
        </div>

        <div class="file-upload-image-preview-body">
            <img src="" alt="{{ __('Preview image') }}" data-preview-image />
        </div>

        <div class="file-upload-image-preview-footer">
            <a href="#" download data-preview-download>{{ __('Download') }}</a>
            <button type="button" data-preview-close>{{ __('Close') }}</button>
        </div>
    </div>
</div>

<style>
    #file-upload-image-preview-modal[hidden] {
        display: none;
    }

    .file-upload-image-preview-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.68);
        z-index: 9998;
    }

    .file-upload-image-preview-dialog {
        position: fixed;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        width: min(92vw, 1080px);
        max-height: 92vh;
        display: flex;
        flex-direction: column;
        background: #ffffff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 16px 56px rgba(2, 6, 23, 0.4);
        z-index: 9999;
    }

    .dark .file-upload-image-preview-dialog {
        background: #0f172a;
        color: #e2e8f0;
    }

    .file-upload-image-preview-header,
    .file-upload-image-preview-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
    }

    .file-upload-image-preview-footer {
        border-top: 1px solid #e2e8f0;
        border-bottom: 0;
    }

    .dark .file-upload-image-preview-header,
    .dark .file-upload-image-preview-footer {
        border-color: #334155;
    }

    .file-upload-image-preview-header h3 {
        margin: 0;
        font-size: 0.98rem;
        font-weight: 700;
    }

    .file-upload-image-preview-close {
        border: none;
        background: transparent;
        font-size: 1.5rem;
        line-height: 1;
        cursor: pointer;
        color: inherit;
    }

    .file-upload-image-preview-body {
        padding: 12px;
        display: grid;
        place-items: center;
        overflow: auto;
    }

    .file-upload-image-preview-body img {
        max-width: 100%;
        max-height: calc(92vh - 130px);
        object-fit: contain;
        border-radius: 8px;
    }

    .file-upload-image-preview-footer a,
    .file-upload-image-preview-footer button {
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #0f172a;
        padding: 0.45rem 0.85rem;
        border-radius: 0.5rem;
        text-decoration: none;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .dark .file-upload-image-preview-footer a,
    .dark .file-upload-image-preview-footer button {
        border-color: #475569;
        background: #1e293b;
        color: #e2e8f0;
    }

    .fi-fo-file-upload[data-image-popup-preview] a.filepond--open-icon {
        -webkit-mask-image: none !important;
        mask-image: none !important;
        -webkit-mask-size: 0 !important;
        mask-size: 0 !important;
        width: auto !important;
        height: auto !important;
        min-height: 1.4rem;
        padding: 0.22rem 0.55rem;
        margin-inline-end: 0.4rem !important;
        border-radius: 9999px;
        border: 1px solid rgba(255, 255, 255, 0.45);
        background: #0f172a !important;
        color: #ffffff !important;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.35);
        text-decoration: none;
        font-size: 0;
        line-height: 1;
        vertical-align: middle;
    }

    .fi-fo-file-upload[data-image-popup-preview] a.filepond--open-icon::after {
        content: 'Preview';
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        text-transform: uppercase;
    }

    html[dir='rtl'] .fi-fo-file-upload[data-image-popup-preview] a.filepond--open-icon::after {
        content: 'معاينة';
        text-transform: none;
    }

    .fi-fo-file-upload[data-image-popup-preview] a.filepond--open-icon:hover,
    .fi-fo-file-upload[data-image-popup-preview] a.filepond--open-icon:focus {
        background: #1e293b !important;
        box-shadow: 0 2px 12px rgba(15, 23, 42, 0.5);
    }

    .dark .fi-fo-file-upload[data-image-popup-preview] a.filepond--open-icon {
        background: #e2e8f0 !important;
        color: #0f172a !important;
        border-color: rgba(15, 23, 42, 0.25);
    }

    .dark .fi-fo-file-upload[data-image-popup-preview] a.filepond--open-icon:hover,
    .dark .fi-fo-file-upload[data-image-popup-preview] a.filepond--open-icon:focus {
        background: #ffffff !important;
    }
</style>

<script>
    (() => {
        if (window.__fileUploadImagePreviewModalInitialized) {
            return;
        }

        window.__fileUploadImagePreviewModalInitialized = true;

        const modal = document.getElementById('file-upload-image-preview-modal');

        if (! modal) {
            return;
        }

        const image = modal.querySelector('[data-preview-image]');
        const downloadLink = modal.querySelector('[data-preview-download]');
        const closeTriggers = modal.querySelectorAll('[data-preview-close]');
        const backdrop = modal.querySelector('[data-preview-backdrop]');
        let lastFocusedElement = null;

        const openModal = (url, trigger) => {
            if (! url) {
                return;
            }

            image.src = url;
            downloadLink.href = url;
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
            lastFocusedElement = trigger ?? document.activeElement;
            closeTriggers[0]?.focus();
        };

        const closeModal = () => {
            modal.hidden = true;
            image.src = '';
            downloadLink.href = '#';
            document.body.style.removeProperty('overflow');

            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            }
        };

        document.addEventListener('click', (event) => {
            const openLink = event.target.closest('a.filepond--open-icon');

            if (! openLink) {
                return;
            }

            const uploadRoot = openLink.closest('.fi-fo-file-upload[data-image-popup-preview]');

            if (! uploadRoot) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            openModal(openLink.href, openLink);
        });

        closeTriggers.forEach((element) => {
            element.addEventListener('click', closeModal);
        });

        backdrop?.addEventListener('click', closeModal);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && ! modal.hidden) {
                closeModal();
            }
        });
    })();
</script>
