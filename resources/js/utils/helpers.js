export function isUSAccount(user = window.user) {
    if (!user) return false;
    return user.accounttype === 'US';
}

export function isMobileView() {
    return window.matchMedia('(max-width: 768px)').matches;
}

export function showPricingForPH(user = window.user) {
    if (!user) return false;

    const accountType = user.accounttype || 'PH';
    const mobile = isMobileView();

    // Desktop: US or PH
    if (!mobile && (accountType === 'US' || accountType === 'PH')) {
        return true;
    }

    // Mobile: only PH
    if (mobile && accountType === 'PH') {
        return true;
    }

    return false;
}