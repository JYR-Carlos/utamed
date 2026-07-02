/**
 * Utility functions for common operations
 * Includes class name merging, formatting, and helper functions
 */

import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

/**
 * Merges Tailwind CSS classes with clsx and tailwind-merge
 * Handles conditional classes and resolves Tailwind conflicts
 * 
 * @param inputs - Class names or conditional class objects
 * @returns Merged class string
 * 
 * @example
 * cn("px-2 py-1", "bg-blue-500"); // "px-2 py-1 bg-blue-500"
 * cn("px-2", { "bg-red-500": isError }); // "px-2 bg-red-500" (if isError is true)
 */
export function cn(...inputs: ClassValue[]) {
	return twMerge(clsx(inputs));
}

/**
 * Formats a number as Chilean currency (CLP)
 * 
 * @param amount - Number to format
 * @returns Formatted currency string
 * 
 * @example
 * formatCurrency(1000); // "$1.000"
 * formatCurrency(1234567); // "$1.234.567"
 */
export function formatCurrency(amount: number): string {
	return new Intl.NumberFormat('es-CL', {
		style: 'currency',
		currency: 'CLP',
		minimumFractionDigits: 0
	}).format(amount);
}

/**
 * Capitalizes the first letter of a string
 * 
 * @param str - String to capitalize
 * @returns Capitalized string
 * 
 * @example
 * capitalize("hello world"); // "Hello world"
 */
export function capitalize(str: string): string {
	if (!str) return '';
	return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Converts a string to title case (capitalizes each word)
 * 
 * @param str - String to convert
 * @returns Title case string
 * 
 * @example
 * titleCase("hello world"); // "Hello World"
 */
export function titleCase(str: string): string {
	if (!str) return '';
	return str
		.toLowerCase()
		.split(' ')
		.map(word => capitalize(word))
		.join(' ');
}

/**
 * Debounces a function call
 * 
 * @param func - Function to debounce
 * @param wait - Wait time in milliseconds
 * @returns Debounced function
 * 
 * @example
 * const debouncedSearch = debounce((query) => search(query), 300);
 */
export function debounce<T extends (...args: any[]) => any>(
	func: T,
	wait: number
): (...args: Parameters<T>) => void {
	let timeout: ReturnType<typeof setTimeout> | null = null;

	return function (...args: Parameters<T>) {
		if (timeout) clearTimeout(timeout);
		timeout = setTimeout(() => func(...args), wait);
	};
}

/**
 * Generates a random ID string
 * 
 * @param length - Length of the ID (default: 8)
 * @returns Random ID string
 * 
 * @example
 * generateId(); // "a3f9d2e1"
 * generateId(12); // "a3f9d2e1b4c5"
 */
export function generateId(length: number = 8): string {
	return Math.random()
		.toString(36)
		.substring(2, 2 + length);
}

/**
 * Sleeps for a specified duration
 * 
 * @param ms - Milliseconds to sleep
 * @returns Promise that resolves after the duration
 * 
 * @example
 * await sleep(1000); // Wait 1 second
 */
export function sleep(ms: number): Promise<void> {
	return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Checks if a value is empty (null, undefined, empty string, empty array, or empty object)
 * 
 * @param value - Value to check
 * @returns true if empty, false otherwise
 */
export function isEmpty(value: any): boolean {
	if (value === null || value === undefined) return true;
	if (typeof value === 'string') return value.trim().length === 0;
	if (Array.isArray(value)) return value.length === 0;
	if (typeof value === 'object') return Object.keys(value).length === 0;
	return false;
}

// Type utilities for component props
export type WithoutChild<T> = T extends { child?: any } ? Omit<T, "child"> : T;
export type WithoutChildren<T> = T extends { children?: any } ? Omit<T, "children"> : T;
export type WithoutChildrenOrChild<T> = WithoutChildren<WithoutChild<T>>;
export type WithElementRef<T, U extends HTMLElement = HTMLElement> = T & { ref?: U | null };

