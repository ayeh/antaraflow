{{-- Global confirm modal — replaces native browser confirm() --}}
<div
    x-data
    x-show="$store.confirmDialog.open"
    x-cloak
    class="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center p-4"
    @keydown.escape.window="$store.confirmDialog.resolve(false)"
    style="display: none;"
>
    {{-- Backdrop --}}
    <div
        class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="$store.confirmDialog.resolve(false)"
    ></div>

    {{-- Modal --}}
    <div
        class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm p-6 border border-gray-100 dark:border-slate-700"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        @click.stop
    >
        {{-- Icon --}}
        <div class="flex items-center justify-center w-12 h-12 rounded-full mx-auto mb-4"
             :class="$store.confirmDialog.isDanger
                ? 'bg-red-100 dark:bg-red-900/30'
                : 'bg-amber-100 dark:bg-amber-900/30'">
            <template x-if="$store.confirmDialog.isDanger">
                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </template>
            <template x-if="!$store.confirmDialog.isDanger">
                <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
            </template>
        </div>

        {{-- Title --}}
        <h3 class="text-base font-semibold text-gray-900 dark:text-white text-center mb-1"
            x-text="$store.confirmDialog.title"></h3>

        {{-- Message --}}
        <p class="text-sm text-gray-500 dark:text-gray-400 text-center mb-6"
           x-text="$store.confirmDialog.message"></p>

        {{-- Buttons --}}
        <div class="flex gap-3">
            <button
                type="button"
                @click="$store.confirmDialog.resolve(false)"
                class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 rounded-xl transition-colors"
            >Cancel</button>
            <button
                type="button"
                @click="$store.confirmDialog.resolve(true)"
                class="flex-1 px-4 py-2.5 text-sm font-medium text-white rounded-xl transition-colors"
                :class="$store.confirmDialog.isDanger
                    ? 'bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600'
                    : 'bg-amber-500 hover:bg-amber-600'"
                x-text="$store.confirmDialog.confirmLabel"
            ></button>
        </div>
    </div>
</div>

<script>
function _registerConfirmStore() {
    Alpine.store('confirmDialog', {
        open: false,
        title: 'Confirm',
        message: '',
        confirmLabel: 'Confirm',
        isDanger: true,
        _resolve: null,

        show(message, options = {}) {
            this.message = message;
            this.title = options.title || 'Confirm';
            this.confirmLabel = options.confirmLabel || (options.isDanger !== false ? 'Delete' : 'Continue');
            this.isDanger = options.isDanger !== false;
            this.open = true;
        },

        resolve(value) {
            this.open = false;
            if (this._resolve) {
                const fn = this._resolve;
                this._resolve = null;
                fn(value);
            }
        },
    });
}

// Register store whether Alpine has already initialized or not
if (window.Alpine) {
    _registerConfirmStore();
} else {
    document.addEventListener('alpine:init', _registerConfirmStore);
}

window.antaraConfirm = function(message, options = {}) {
    return new Promise(resolve => {
        const store = Alpine.store('confirmDialog');
        store._resolve = resolve;
        store.show(message, options);
    });
};

window.confirmThenSubmit = function(event, message, options = {}) {
    event.preventDefault();
    const form = event.target;
    window.antaraConfirm(message, options).then(ok => {
        if (ok) form.submit();
    });
};
</script>
