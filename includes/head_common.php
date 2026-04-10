<style>
    .toast-enter { animation: toastSlide 380ms ease-out both; }
    @keyframes toastSlide {
        from { opacity: 0; transform: translate(-50%, -12px); }
        to { opacity: 1; transform: translate(-50%, 0); }
    }
    #mobile-nav-panel:not(.hidden) { animation: navFade 200ms ease-out both; }
    @keyframes navFade {
        from { opacity: 0; }
        to { opacity: 1; }
    }
</style>
