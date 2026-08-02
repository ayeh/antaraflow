{{-- Global confirm modal — replaces native browser confirm() --}}
{{--
    Usage:
      window.antaraConfirm('Message', {
          title: 'Title',                          // optional
          confirmLabel: 'Yes, delete',             // optional
          type: 'danger|warning|info|success',     // optional (default: danger)
      }).then(ok => { if (ok) ... });

      // Or for forms:
      onsubmit="confirmThenSubmit(event, 'Message')"
--}}

<style>
@keyframes _cm-spring {
    0%   { opacity: 0; transform: scale(.88) translateY(10px); }
    55%  { opacity: 1; transform: scale(1.03) translateY(-3px); }
    75%  { transform: scale(.98) translateY(1px); }
    100% { transform: scale(1)   translateY(0);   }
}
@keyframes _cm-leave {
    from { opacity: 1; transform: scale(1); }
    to   { opacity: 0; transform: scale(.92) translateY(6px); }
}
@keyframes _cm-shake {
    0%,100% { transform: translateX(0); }
    15%     { transform: translateX(-7px) rotate(-.4deg); }
    30%     { transform: translateX( 7px) rotate( .4deg); }
    45%     { transform: translateX(-5px); }
    60%     { transform: translateX( 5px); }
    75%     { transform: translateX(-3px); }
    90%     { transform: translateX( 2px); }
}
@keyframes _cm-icon {
    0%   { opacity: 0; transform: scale(.4) rotate(-12deg); }
    60%  { opacity: 1; transform: scale(1.18) rotate(4deg); }
    80%  { transform: scale(.96) rotate(-1deg); }
    100% { transform: scale(1) rotate(0); }
}
@keyframes _cm-strip {
    from { transform: scaleX(0); transform-origin: left; }
    to   { transform: scaleX(1); transform-origin: left; }
}

._cm-enter { animation: _cm-spring .35s cubic-bezier(.34,1.56,.64,1) both; }
._cm-leave { animation: _cm-leave .18s ease-in  both; }
._cm-shake { animation: _cm-shake .42s ease-in-out; }
._cm-icon  { animation: _cm-icon  .38s cubic-bezier(.34,1.56,.64,1) .08s both; }
._cm-strip { animation: _cm-strip .3s ease-out .05s both; }
</style>

<div
    x-data="{
        cardClass: '',
        _onOpen()  { this.cardClass = '_cm-enter'; },
        _onClose() { this.cardClass = '_cm-leave'; },
        _shake()   {
            if ($store.confirmDialog.loading) return;
            this.cardClass = '_cm-shake';
        },
    }"
    x-show="$store.confirmDialog.open"
    x-cloak
    x-init="
        $watch('$store.confirmDialog.open', v => {
            if (v) $nextTick(() => _onOpen());
            else   _onClose();
        })
    "
    class="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center p-4"
    @keydown.escape.window="if ($store.confirmDialog.open && !$store.confirmDialog.loading) $store.confirmDialog.dismiss()"
    @keydown.enter.window="if ($store.confirmDialog.open && !$store.confirmDialog.loading) $store.confirmDialog.confirm()"
    style="display:none"
>
    {{-- Backdrop --}}
    <div
        class="absolute inset-0 bg-black/55 backdrop-blur-[3px]"
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-180"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="_shake()"
    ></div>

    {{-- Card --}}
    <div
        class="relative w-full max-w-sm bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-slate-700 overflow-hidden"
        :class="cardClass"
        @animationend="if (cardClass === '_cm-shake') cardClass = ''"
        @click.stop
    >
        {{-- Coloured strip top --}}
        <div class="_cm-strip h-[3px] w-full"
             :class="{
                 'bg-red-500':    $store.confirmDialog.type === 'danger',
                 'bg-amber-400':  $store.confirmDialog.type === 'warning',
                 'bg-violet-500': $store.confirmDialog.type === 'info',
                 'bg-green-500':  $store.confirmDialog.type === 'success',
             }"></div>

        <div class="px-6 pt-6 pb-5">

            {{-- Icon --}}
            <div class="_cm-icon flex items-center justify-center w-14 h-14 rounded-2xl mx-auto mb-4 ring-4 ring-white dark:ring-slate-800"
                 :class="{
                     'bg-red-100    dark:bg-red-900/30':    $store.confirmDialog.type === 'danger',
                     'bg-amber-100  dark:bg-amber-900/30':  $store.confirmDialog.type === 'warning',
                     'bg-violet-100 dark:bg-violet-900/30': $store.confirmDialog.type === 'info',
                     'bg-green-100  dark:bg-green-900/30':  $store.confirmDialog.type === 'success',
                 }">

                <template x-if="$store.confirmDialog.type === 'danger'">
                    <svg class="w-7 h-7 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </template>
                <template x-if="$store.confirmDialog.type === 'warning'">
                    <svg class="w-7 h-7 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                </template>
                <template x-if="$store.confirmDialog.type === 'info'">
                    <svg class="w-7 h-7 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/>
                    </svg>
                </template>
                <template x-if="$store.confirmDialog.type === 'success'">
                    <svg class="w-7 h-7 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </template>
            </div>

            {{-- Title --}}
            <h3 class="text-[15px] font-semibold text-gray-900 dark:text-white text-center leading-snug mb-1.5"
                x-text="$store.confirmDialog.title"></h3>

            {{-- Message --}}
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center leading-relaxed mb-5"
               x-text="$store.confirmDialog.message"></p>

            {{-- Buttons --}}
            <div class="flex flex-col-reverse sm:flex-row gap-2.5">

                {{-- Cancel --}}
                <button
                    type="button"
                    :disabled="$store.confirmDialog.loading"
                    @click="$store.confirmDialog.dismiss()"
                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium
                           text-gray-700 dark:text-gray-200
                           bg-gray-100 dark:bg-slate-700
                           hover:bg-gray-200 dark:hover:bg-slate-600
                           active:scale-[0.96]
                           transition-all duration-100
                           disabled:opacity-40 disabled:cursor-not-allowed
                           select-none"
                >
                    {{ __('Cancel') }}
                    <kbd class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono
                                bg-gray-200 dark:bg-slate-600 text-gray-400 dark:text-gray-500 leading-none">Esc</kbd>
                </button>

                {{-- Confirm --}}
                <button
                    type="button"
                    :disabled="$store.confirmDialog.loading"
                    @click="$store.confirmDialog.confirm()"
                    class="flex-1 relative inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-white
                           shadow-lg active:scale-[0.96]
                           transition-all duration-100
                           disabled:cursor-wait
                           select-none overflow-hidden"
                    :class="{
                        'bg-red-600    hover:bg-red-700    dark:bg-red-700    dark:hover:bg-red-600    shadow-red-200/60    dark:shadow-red-900/40    disabled:opacity-70': $store.confirmDialog.type === 'danger',
                        'bg-amber-500  hover:bg-amber-600  dark:bg-amber-600  dark:hover:bg-amber-500  shadow-amber-200/60  dark:shadow-amber-900/40  disabled:opacity-70': $store.confirmDialog.type === 'warning',
                        'bg-violet-600 hover:bg-violet-700 dark:bg-violet-700 dark:hover:bg-violet-600 shadow-violet-200/60 dark:shadow-violet-900/40 disabled:opacity-70': $store.confirmDialog.type === 'info',
                        'bg-green-600  hover:bg-green-700  dark:bg-green-700  dark:hover:bg-green-600  shadow-green-200/60  dark:shadow-green-900/40  disabled:opacity-70': $store.confirmDialog.type === 'success',
                    }"
                >
                    <span x-show="!$store.confirmDialog.loading" class="flex items-center gap-2">
                        <span x-text="$store.confirmDialog.confirmLabel"></span>
                        <kbd class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono
                                    bg-white/20 text-white/80 leading-none">↵</kbd>
                    </span>

                    <span x-show="$store.confirmDialog.loading" x-cloak class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        {{ __('Processing…') }}
                    </span>

                    {{-- Ripple layer --}}
                    <span class="absolute inset-0 rounded-xl opacity-0 hover:opacity-10 bg-white transition-opacity duration-150 pointer-events-none"></span>
                </button>
            </div>

            {{-- Keyboard hint --}}
            <p class="text-center text-[11px] text-gray-300 dark:text-gray-600 mt-3.5 select-none">
                {{ __('Click outside or press') }} <span class="font-mono">Esc</span> {{ __('to cancel') }}
            </p>
        </div>
    </div>
</div>

<script>
(function () {
    function _register() {
        Alpine.store('confirmDialog', {
            open:         false,
            title:        '{{ __("Confirm") }}',
            message:      '',
            confirmLabel: '{{ __("Confirm") }}',
            type:         'danger',   // danger | warning | info | success
            loading:      false,
            _resolve:     null,

            show(message, options = {}) {
                const isDanger = options.isDanger !== false;
                this.message      = message;
                this.title        = options.title        || '{{ __("Confirm") }}';
                this.confirmLabel = options.confirmLabel || (isDanger ? '{{ __("Delete") }}' : '{{ __("Continue") }}');
                this.type         = options.type         || (isDanger ? 'danger' : 'warning');
                this.loading      = false;
                this.open         = true;
            },

            async confirm() {
                if (this.loading || !this._resolve) return;
                this.loading = true;
                // Brief visual beat so the user sees the loading state before the
                // dialog closes and the page action begins.
                await new Promise(r => setTimeout(r, 140));
                this._close(true);
            },

            dismiss() {
                if (this.loading) return;
                this._close(false);
            },

            _close(value) {
                this.open    = false;
                this.loading = false;
                if (this._resolve) {
                    const fn    = this._resolve;
                    this._resolve = null;
                    fn(value);
                }
            },

            // Legacy shim — old code calls resolve(bool) directly.
            resolve(value) {
                if (value) this.confirm();
                else        this.dismiss();
            },
        });
    }

    if (window.Alpine) _register();
    else document.addEventListener('alpine:init', _register);
})();

/**
 * Promise-based confirm dialog.
 *
 * @param {string} message
 * @param {object} options  title, confirmLabel, type (danger|warning|info|success), isDanger
 * @returns {Promise<boolean>}
 */
window.antaraConfirm = function (message, options = {}) {
    return new Promise(resolve => {
        const store     = Alpine.store('confirmDialog');
        store._resolve  = resolve;
        store.show(message, options);
    });
};

/**
 * Show the confirm dialog before submitting a form.
 * Use as:  onsubmit="confirmThenSubmit(event, 'Message')"
 */
window.confirmThenSubmit = function (event, message, options = {}) {
    event.preventDefault();
    const form = event.target;
    window.antaraConfirm(message, options).then(ok => { if (ok) form.submit(); });
};
</script>
