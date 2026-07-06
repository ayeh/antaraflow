<style>
    @keyframes lobbyConfettiFall {
        0% { transform: translateY(-12vh) rotate(0deg); opacity: 1; }
        100% { transform: translateY(110vh) rotate(720deg); opacity: 0; }
    }
    .lobby-confetti-piece {
        position: fixed; top: 0;
        animation-name: lobbyConfettiFall;
        animation-timing-function: ease-in;
        animation-fill-mode: forwards;
        pointer-events: none;
    }
    @keyframes lobbyPop {
        0% { transform: scale(0.6) translateY(14px); opacity: 0; }
        60% { transform: scale(1.05) translateY(-2px); opacity: 1; }
        100% { transform: scale(1) translateY(0); opacity: 1; }
    }
    .lobby-attendee-card { animation: lobbyPop 0.5s cubic-bezier(0.22, 1, 0.36, 1) both; }

    /* (#4) Newest registrant highlight */
    @keyframes lobbyNewGlow {
        0%, 100% { box-shadow: 0 0 0 1px rgba(52,211,153,0.4), 0 0 18px rgba(52,211,153,0.25); }
        50% { box-shadow: 0 0 0 2px rgba(52,211,153,0.7), 0 0 28px rgba(52,211,153,0.5); }
    }
    .lobby-card-new {
        border-color: rgba(52,211,153,0.6) !important;
        animation: lobbyNewGlow 1.4s ease-in-out infinite;
    }

    /* (#3) QR breathing glow */
    @keyframes lobbyQrGlow {
        0%, 100% { box-shadow: 0 0 28px 6px color-mix(in srgb, var(--lobby-primary, #8b5cf6) 35%, transparent), 0 0 60px 12px color-mix(in srgb, var(--lobby-secondary, #ec4899) 18%, transparent); }
        50% { box-shadow: 0 0 44px 12px color-mix(in srgb, var(--lobby-primary, #8b5cf6) 60%, transparent), 0 0 90px 20px color-mix(in srgb, var(--lobby-secondary, #ec4899) 32%, transparent); }
    }
    .lobby-qr-glow { animation: lobbyQrGlow 2.8s ease-in-out infinite; }

    /* (#1) Hero welcome card */
    @keyframes lobbyHeroIn {
        0% { transform: scale(0.7); opacity: 0; }
        55% { transform: scale(1.04); opacity: 1; }
        100% { transform: scale(1); opacity: 1; }
    }
    .lobby-hero-card { animation: lobbyHeroIn 0.5s cubic-bezier(0.22, 1, 0.36, 1) both; }

    /* (#6) Idle bobbing prompt */
    @keyframes lobbyBob {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }
    .lobby-bob { animation: lobbyBob 1.6s ease-in-out infinite; }

    @media (prefers-reduced-motion: reduce) {
        .lobby-qr-glow, .lobby-card-new, .lobby-bob, .lobby-hero-card, .lobby-attendee-card { animation: none; }
    }
</style>
