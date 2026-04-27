export function formatName(fullName?: string): string {
    if (!fullName) return '';
    
    return fullName.trim().split(' ').map(name => name.charAt(0).toUpperCase() + name.slice(1)).join(' ');
}

export function useFormatName() {
    return { formatName };
}