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
    body.w3fa-modal-open { overflow: hidden; }
    .w3fa-modal-panel { animation: w3faModalIn 240ms ease-out both; }
    @keyframes w3faModalIn {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @media (min-width: 768px) {
        .w3fa-modal-panel { animation-name: w3faModalInMd; }
    }
    @keyframes w3faModalInMd {
        from { opacity: 0; transform: translateY(8px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
</style>
