export const assetBaseUrl = String(
    import.meta.env.VITE_ASSET_URL
        ?? (typeof window !== 'undefined' ? window.location.origin : '')
).replace(/\/$/, '')
