declare global {
    interface Window {
        $confirm: (opts?: any) => Promise<boolean>
    }
}
