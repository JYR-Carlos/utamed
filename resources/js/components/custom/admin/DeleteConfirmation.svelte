<script lang="ts">
    interface Props {
        isOpen: boolean;
        title: string;
        message: string;
        onConfirm: () => void;
        onCancel: () => void;
        isLoading?: boolean;
    }

    let { isOpen = $bindable(), title, message, onConfirm, onCancel, isLoading = false }: Props = $props();

    function handleBackdropClick(e: MouseEvent) {
        if (e.target === e.currentTarget && !isLoading) {
            onCancel();
        }
    }
</script>

{#if isOpen}
    <div class="modal-backdrop" onclick={handleBackdropClick} role="presentation">
        <div class="modal-content" role="alertdialog" aria-modal="true" aria-labelledby="dialog-title">
            <div class="icon-container">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="48"
                    height="48"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="warning-icon"
                >
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>

            <h2 id="dialog-title" class="title">{title}</h2>
            <p class="message">{message}</p>

            <div class="actions">
                <button onclick={onCancel} class="btn-cancel" disabled={isLoading}>Cancelar</button>
                <button onclick={onConfirm} class="btn-confirm" disabled={isLoading}>
                    {#if isLoading}
                        <span class="spinner"></span>
                    {/if}
                    Eliminar
                </button>
            </div>
        </div>
    </div>
{/if}

<style>
    .modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 50;
        padding: 1rem;
        animation: fadeIn 0.2s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        box-shadow:
            0 20px 25px -5px rgba(0, 0, 0, 0.1),
            0 10px 10px -5px rgba(0, 0, 0, 0.04);
        max-width: 400px;
        width: 100%;
        padding: 2rem;
        text-align: center;
        animation: slideUp 0.2s ease-out;
    }

    @keyframes slideUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .icon-container {
        display: flex;
        justify-content: center;
        margin-bottom: 1rem;
    }

    .warning-icon {
        color: #dc2626;
    }

    .title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #111827;
        margin: 0 0 0.5rem 0;
    }

    .message {
        color: #6b7280;
        font-size: 0.875rem;
        margin: 0 0 1.5rem 0;
        line-height: 1.5;
    }

    .actions {
        display: flex;
        gap: 0.75rem;
        justify-content: center;
    }

    .btn-cancel,
    .btn-confirm {
        padding: 0.625rem 1.5rem;
        border: none;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-cancel {
        background: white;
        color: #374151;
        border: 1px solid #d1d5db;
    }

    .btn-cancel:hover:not(:disabled) {
        background: #f9fafb;
    }

    .btn-confirm {
        background: #dc2626;
        color: white;
    }

    .btn-confirm:hover:not(:disabled) {
        background: #b91c1c;
    }

    .btn-cancel:disabled,
    .btn-confirm:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .spinner {
        width: 14px;
        height: 14px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>
