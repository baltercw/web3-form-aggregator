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
    #w3fa-task-modal:not(.hidden) {
        pointer-events: auto;
    }
    #w3fa-task-modal-backdrop {
        pointer-events: auto;
    }
    #w3fa-task-modal-panel {
        pointer-events: auto;
        max-height: 90dvh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    #w3fa-modal-scroll {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }
    body.w3fa-modal-open .w3fa-bg-blob {
        animation-play-state: paused !important;
    }
    body.w3fa-modal-open .w3fa-bg-canvas-wrap {
        opacity: 0 !important;
        transition: opacity 200ms ease-out;
    }
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
    /* Windows/Chrome 下拉選單常出現白底白字，統一指定 option 顏色 */
    select,
    option,
    optgroup {
        color: #f4f4f5;
        background-color: #18181b;
    }
</style>
