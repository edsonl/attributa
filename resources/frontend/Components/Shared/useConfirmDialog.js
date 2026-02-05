import { inject } from 'vue'
export function useConfirmDialog () {
    const confirm = inject('confirmDialog')
    return { confirm }
}
