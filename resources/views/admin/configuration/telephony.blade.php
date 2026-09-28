            <form method="POST" action="{{ route('admin.configuration.telephony-features.update') }}" class="space-y-5">
                @csrf
                @php
                    $featureLabels = [
                        'session_controls' => 'ViciDial Session Controls (login, pause, pause code, logout)',
                        'ingroup_management' => 'In-group Management',
                        'transfer_controls' => 'Transfer and Conference Controls',
                        'recording_controls' => 'Recording Controls',
                        'dtmf_controls' => 'DTMF Keypad',
                        'callback_controls' => 'Callback Scheduling',
                        'lead_tools' => 'Lead Search and Lead Tools',
                        'predictive_dialing' => 'Predictive Dialing',
                        'agent_screen_access' => 'Agent Screen Access',
                    ];
                @endphp

                <x-alert type="info" title="Feature Gating">
                    Disabled telephony features are hidden from the Agent Screen and blocked at API level for non-Super Admin users. Agent Screen Access also controls Agent Capture webforms.
                </x-alert>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($featureLabels as $featureKey => $featureLabel)
                        <label class="flex items-center justify-between rounded-lg border border-[var(--color-border)] p-3">
                            <span class="text-sm text-[var(--color-on-surface)]">{{ $featureLabel }}</span>
                            <input type="checkbox"
                                   name="features[{{ $featureKey }}]"
                                   value="1"
                                   class="h-4 w-4 rounded border-[var(--color-border)]"
                                   @checked(($telephonyFeatures[$featureKey] ?? true) === true)>
                        </label>
                    @endforeach
                </div>

                <div>
                    <button type="submit" class="btn-primary">Save Telephony Feature Access</button>
                </div>
            </form>
