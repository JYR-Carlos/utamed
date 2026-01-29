// Svelte 5 Runes Type Declarations
// These are compiler-level functions that are transformed during compilation

declare function $props<T = Record<string, any>>(): T;
declare function $state<T>(initial: T): T;
declare function $state<T>(): T | undefined;
declare function $derived<T>(expression: T): T;
declare function $effect(fn: () => void | (() => void)): void;
declare function $bindable<T>(initial?: T): T;
declare function $inspect(...values: any[]): void;
declare function $host(): HTMLElement;
