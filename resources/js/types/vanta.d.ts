declare module 'vanta/dist/vanta.net.min' {
    interface VantaNetOptions {
        el: HTMLElement;
        THREE: unknown;
        color?: number;
        backgroundColor?: number;
        points?: number;
        maxDistance?: number;
        spacing?: number;
        showDots?: boolean;
        mouseControls?: boolean;
        touchControls?: boolean;
        gyroControls?: boolean;
        minHeight?: number;
        minWidth?: number;
        scale?: number;
        scaleMobile?: number;
    }

    interface VantaEffect {
        destroy(): void;
        resize(): void;
    }

    function NET(options: VantaNetOptions): VantaEffect;
    export default NET;
}
