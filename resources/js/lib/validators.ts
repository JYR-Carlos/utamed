/**
 * Validation utilities for forms
 * Contains common validation functions for Chilean-specific formats and general validations
 */

/**
 * Validates a Chilean RUT (Rol Único Tributario)
 * 
 * @param rut - RUT string with or without formatting (e.g., "12.345.678-9" or "123456789")
 * @returns true if RUT is valid, false otherwise
 * 
 * @example
 * validateRut("12.345.678-5"); // true
 * validateRut("12345678-5");   // true
 * validateRut("12345678-0");   // false (invalid check digit)
 */
export function validateRut(rut: string): boolean {
    if (!rut || typeof rut !== 'string') return false;

    // Remove formatting
    const cleanRut = rut.replace(/[.-]/g, '');

    // Check format: at least 2 characters (number + check digit)
    if (cleanRut.length < 2) return false;

    // Split into body and check digit
    const body = cleanRut.slice(0, -1);
    const checkDigit = cleanRut.slice(-1).toUpperCase();

    // Validate body is numeric
    if (!/^\d+$/.test(body)) return false;

    // Calculate expected check digit
    let sum = 0;
    let multiplier = 2;

    for (let i = body.length - 1; i >= 0; i--) {
        sum += parseInt(body[i]) * multiplier;
        multiplier = multiplier === 7 ? 2 : multiplier + 1;
    }

    const expectedCheckDigit = 11 - (sum % 11);
    let expectedCheckDigitStr: string;

    if (expectedCheckDigit === 11) {
        expectedCheckDigitStr = '0';
    } else if (expectedCheckDigit === 10) {
        expectedCheckDigitStr = 'K';
    } else {
        expectedCheckDigitStr = expectedCheckDigit.toString();
    }

    return checkDigit === expectedCheckDigitStr;
}

/**
 * Formats a Chilean RUT with dots and dash
 * 
 * @param rut - RUT string without formatting
 * @returns Formatted RUT (e.g., "12.345.678-9")
 * 
 * @example
 * formatRut("123456789"); // "12.345.678-9"
 */
export function formatRut(rut: string): string {
    if (!rut) return '';

    // Remove any existing formatting
    const cleanRut = rut.replace(/[.-]/g, '');

    if (cleanRut.length < 2) return cleanRut;

    // Split into body and check digit
    const body = cleanRut.slice(0, -1);
    const checkDigit = cleanRut.slice(-1);

    // Add dots to body
    const formattedBody = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `${formattedBody}-${checkDigit}`;
}

/**
 * Cleans a RUT string by removing formatting characters
 * 
 * @param rut - RUT string with or without formatting
 * @returns Clean RUT without dots or dashes
 * 
 * @example
 * cleanRut("12.345.678-9"); // "123456789"
 */
export function cleanRut(rut: string): string {
    if (!rut) return '';
    return rut.replace(/[.-]/g, '');
}

/**
 * Validates an email address
 * 
 * @param email - Email string to validate
 * @returns true if email is valid, false otherwise
 * 
 * @example
 * validateEmail("user@example.com"); // true
 * validateEmail("invalid-email");     // false
 */
export function validateEmail(email: string): boolean {
    if (!email || typeof email !== 'string') return false;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Validates a Chilean phone number
 * Accepts formats: +56912345678, 912345678, +56 9 1234 5678
 * 
 * @param phone - Phone number string
 * @returns true if phone is valid, false otherwise
 * 
 * @example
 * validatePhone("+56912345678");  // true
 * validatePhone("912345678");      // true
 * validatePhone("123");            // false
 */
export function validatePhone(phone: string): boolean {
    if (!phone || typeof phone !== 'string') return false;

    // Remove spaces, dashes, and parentheses
    const cleanPhone = phone.replace(/[\s\-()]/g, '');

    // Chilean mobile: +56 9 XXXX XXXX or 9 XXXX XXXX
    const mobileRegex = /^(\+?56)?9\d{8}$/;

    // Chilean landline: +56 XX XXX XXXX or XX XXX XXXX
    const landlineRegex = /^(\+?56)?\d{9}$/;

    return mobileRegex.test(cleanPhone) || landlineRegex.test(cleanPhone);
}

/**
 * Validates a password meets minimum requirements
 * 
 * @param password - Password string
 * @param minLength - Minimum length (default: 8)
 * @returns Object with validation result and error message
 * 
 * @example
 * validatePassword("weak");           // { valid: false, message: "..." }
 * validatePassword("StrongPass123!");  // { valid: true, message: "" }
 */
export function validatePassword(
    password: string,
    minLength: number = 8
): { valid: boolean; message: string } {
    if (!password) {
        return { valid: false, message: 'La contraseña es requerida' };
    }

    if (password.length < minLength) {
        return {
            valid: false,
            message: `La contraseña debe tener al menos ${minLength} caracteres`
        };
    }

    // Check for at least one uppercase letter
    if (!/[A-Z]/.test(password)) {
        return {
            valid: false,
            message: 'La contraseña debe contener al menos una mayúscula'
        };
    }

    // Check for at least one lowercase letter
    if (!/[a-z]/.test(password)) {
        return {
            valid: false,
            message: 'La contraseña debe contener al menos una minúscula'
        };
    }

    // Check for at least one number
    if (!/\d/.test(password)) {
        return {
            valid: false,
            message: 'La contraseña debe contener al menos un número'
        };
    }

    return { valid: true, message: '' };
}

/**
 * Validates that two passwords match
 * 
 * @param password - First password
 * @param confirmation - Password confirmation
 * @returns true if passwords match, false otherwise
 */
export function validatePasswordConfirmation(
    password: string,
    confirmation: string
): boolean {
    return password === confirmation && password.length > 0;
}

/**
 * Validates a URL
 * 
 * @param url - URL string to validate
 * @returns true if URL is valid, false otherwise
 * 
 * @example
 * validateUrl("https://example.com"); // true
 * validateUrl("not-a-url");           // false
 */
export function validateUrl(url: string): boolean {
    if (!url || typeof url !== 'string') return false;

    try {
        new URL(url);
        return true;
    } catch {
        return false;
    }
}

/**
 * Validates a number is within a range
 * 
 * @param value - Number to validate
 * @param min - Minimum value (inclusive)
 * @param max - Maximum value (inclusive)
 * @returns true if number is within range, false otherwise
 * 
 * @example
 * validateNumberRange(5, 1, 10);  // true
 * validateNumberRange(15, 1, 10); // false
 */
export function validateNumberRange(
    value: number,
    min: number,
    max: number
): boolean {
    return value >= min && value <= max;
}

/**
 * Validates a string is not empty or only whitespace
 * 
 * @param value - String to validate
 * @returns true if string has content, false otherwise
 */
export function validateRequired(value: string): boolean {
    return typeof value === 'string' && value.trim().length > 0;
}

/**
 * Validates a string length is within bounds
 * 
 * @param value - String to validate
 * @param min - Minimum length (default: 0)
 * @param max - Maximum length (default: Infinity)
 * @returns true if length is valid, false otherwise
 */
export function validateLength(
    value: string,
    min: number = 0,
    max: number = Infinity
): boolean {
    if (typeof value !== 'string') return false;
    const length = value.trim().length;
    return length >= min && length <= max;
}
