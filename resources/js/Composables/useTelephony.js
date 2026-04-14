import { computed } from 'vue';
import { useAlpineStore } from './useAlpineStore';

/**
 * Reactive view of Alpine `call` + `vicidial` stores for Inertia pages.
 */
export function useTelephony() {
    const { state: call, dispatch: callDispatch } = useAlpineStore('call');
    const { state: vicidial } = useAlpineStore('vicidial');

    const isOnCall = computed(() => ['ringing', 'connected', 'hold', 'wrapup'].includes(call.value?.state));

    return {
        call,
        vicidial,
        callDispatch,
        isOnCall,
        hangupWebRTC: () => callDispatch('hangupWebRTC'),
        toggleMuteWebRTC: () => callDispatch('toggleMuteWebRTC'),
        toggleHoldWebRTC: () => callDispatch('toggleHoldWebRTC'),
    };
}
