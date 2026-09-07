<div id="org-designer-root" class="org-designer">
    <script type="application/json" id="org-designer-boot">@json($this->bootPayload())</script>

    <header>
        <div class="brand">
            <div class="brand-title">{{ __('org_designer.title') }}</div>
        </div>
        <label class="file-upload">
            <span class="btn secondary">{{ __('org_designer.upload_excel') }}</span>
            <input type="file" id="fileInput" wire:model="excelFile" accept=".xlsx,.xlsm,.xls" />
        </label>
        <select id="iltSelect" title="{{ __('org_designer.switch_ilt') }}"></select>
        <button class="btn ghost" id="btnBigPicture" type="button">{{ __('org_designer.big_picture') }}</button>
        <button class="btn ghost" id="btnTeamView" type="button" style="display:none">{{ __('org_designer.back_to_teams') }}</button>
        <div class="zoom-ctrl" title="{{ __('org_designer.zoom') }}">
            <button id="btnZoomOut" type="button">−</button>
            <span class="zoom-value" id="zoomVal">100%</span>
            <button id="btnZoomIn" type="button">+</button>
            <button id="btnZoomReset" type="button" title="{{ __('org_designer.reset_zoom') }}">⟳</button>
        </div>
        <span class="spacer"></span>
        <div class="save-status" id="saveStatus" title="{{ __('org_designer.saved') }}">
            <span class="dot"></span><span id="saveStatusText">{{ __('org_designer.saved') }}</span>
        </div>
        <button class="btn ghost" id="btnUndo" type="button" title="{{ __('org_designer.undo') }}">↶</button>
        <button class="btn ghost" id="btnRedo" type="button" title="{{ __('org_designer.redo') }}">↷</button>
        <button class="btn secondary" id="btnAddTeam" type="button">{{ __('org_designer.add_team') }}</button>
        <button class="btn secondary" id="btnEditHead" type="button">{{ __('org_designer.head') }}</button>
        <div class="save-menu">
            <button class="btn" id="btnSaveMenu" type="button">{{ __('org_designer.save_load') }}</button>
            <div class="save-menu-content" id="saveMenuContent">
                <button type="button" data-act="save-project">{{ __('org_designer.save_project') }}</button>
                <button type="button" data-act="load-project">{{ __('org_designer.load_project') }}</button>
                <div class="divider"></div>
                <button type="button" data-act="export-excel">{{ __('org_designer.export_excel') }}</button>
                <div class="divider"></div>
                <button type="button" data-act="new-project" style="color:var(--hilti-red)">{{ __('org_designer.clear_everything') }}</button>
            </div>
            <input type="file" class="hidden-input" id="projectFileInput" wire:model="projectFile" accept=".json,application/json" />
        </div>
        <button class="btn ghost" id="btnSettings" type="button" title="{{ __('org_designer.settings') }}">⚙</button>
        @foreach (config('app.available_locales') as $locale)
            <form method="POST" action="{{ route('locale.update', ['locale' => $locale]) }}" data-flush-persist>
                @csrf
                <button
                    type="submit"
                    class="btn ghost {{ app()->getLocale() === $locale ? 'secondary' : '' }}"
                >{{ __('common.locales.'.$locale) }}</button>
            </form>
        @endforeach
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn ghost">{{ __('common.actions.logout') }}</button>
        </form>
    </header>

    <div id="bulkBar">
        <span><b id="bulkCount">0</b> {{ __('org_designer.selected', ['count' => '']) }}</span>
        <label>{{ __('org_designer.move_to') }}:
            <select id="bulkMoveTeam" class="to-team-select"></select>
        </label>
        <button type="button" id="bulkDuplicate">{{ __('org_designer.duplicate') }}</button>
        <button type="button" id="bulkDelete">{{ __('common.actions.delete') }}</button>
        <button type="button" id="bulkFlip">{{ __('org_designer.flip_int_ext') }}</button>
        <button type="button" id="bulkClear" style="margin-left:auto">{{ __('org_designer.clear_selection') }}</button>
    </div>

    <main id="canvas">
        <div id="zoomWrap">
            <div id="teamView">
                <div class="ilt-title-bar">
                    <h2 id="iltTitle" title="{{ __('org_designer.rename.title') }}">{{ __('org_designer.no_file_loaded') }}<span class="edit-hint">{{ __('org_designer.rename_hint') }}</span></h2>
                    <div class="ilt-head-box" id="iltHeadBox" style="display:none" title="{{ __('org_designer.head_modal.title') }}">
                        <div class="icon" id="iltHeadIcon"></div>
                        <div>
                            <div style="font-size:10px;color:#888;text-transform:uppercase;">{{ __('org_designer.head_of_area') }}</div>
                            <div class="role" id="iltHeadRole">—</div>
                            <div class="grade" id="iltHeadGrade">—</div>
                        </div>
                    </div>
                </div>
                <div id="teamsRow"></div>
                <div class="empty-state" id="emptyState">
                    <h2>{{ __('org_designer.welcome_heading') }}</h2>
                    <p>{{ __('org_designer.welcome_body') }}</p>
                    <p>{{ __('org_designer.expected_sheet', ['sheet' => $this->project->config['sheetName'] ?? \App\Support\OrgDesigner\OrgDesignerDocument::defaultConfig()['sheetName']]) }}</p>
                </div>
            </div>
            <div id="bigPicture"></div>
        </div>
    </main>

    <div class="modal-backdrop" id="uploadModal">
        <div class="modal">
            <h3>{{ __('org_designer.import.title') }}</h3>
            <p style="font-size:13px;color:#555;margin:0 0 6px 0">
                {!! str_replace(':count', '<b id="existingAreaCount">0</b>', e(__('org_designer.import.existing', ['count' => ':count']))) !!}
            </p>
            <div class="upload-choice-row">
                <div class="upload-choice selected" data-mode="add">
                    <div class="title">{{ __('org_designer.import.add_title') }}</div>
                    <div class="desc">{{ __('org_designer.import.add_desc') }}</div>
                </div>
                <div class="upload-choice" data-mode="replace">
                    <div class="title">{{ __('org_designer.import.replace_title') }}</div>
                    <div class="desc">{{ __('org_designer.import.replace_desc') }}</div>
                </div>
            </div>
            <div class="actions">
                <button class="btn ghost" type="button" id="uploadCancel">{{ __('common.actions.cancel') }}</button>
                <button class="btn" type="button" id="uploadConfirm">{{ __('common.actions.confirm') }}</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="cardModal">
        <div class="modal">
            <h3 id="cardModalTitle">{{ __('org_designer.card.edit_title') }}</h3>
            <div class="field">
                <label>{{ __('org_designer.card.role_type') }}</label>
                <input type="text" id="cardRole" list="roleList" />
                <datalist id="roleList"></datalist>
            </div>
            <div class="row-3">
                <div class="field"><label>{{ __('org_designer.card.grade') }}</label><input type="text" id="cardGrade" /></div>
                <div class="field"><label>{{ __('org_designer.card.fte') }}</label><input type="text" id="cardFTE" value="1" /></div>
                <div class="field">
                    <label>{{ __('org_designer.card.int_ext') }}</label>
                    <select id="cardIntExt">
                        <option value="Internal">{{ __('org_designer.card.internal') }}</option>
                        <option value="External">{{ __('org_designer.card.external') }}</option>
                    </select>
                </div>
            </div>
            <div class="field">
                <label>{{ __('org_designer.card.location') }}</label>
                <input type="text" id="cardLocation" list="locationList" />
                <datalist id="locationList"></datalist>
            </div>
            <div class="field"><label>{{ __('org_designer.card.notes') }}</label><textarea id="cardNotes"></textarea></div>
            <div class="actions">
                <button class="btn ghost" type="button" id="cardDuplicate" style="margin-right:auto">{{ __('org_designer.duplicate') }}</button>
                <button class="btn ghost" type="button" id="cardCancel">{{ __('common.actions.cancel') }}</button>
                <button class="btn" type="button" id="cardSave">{{ __('common.actions.save') }}</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="teamModal">
        <div class="modal">
            <h3 id="teamModalTitle">{{ __('org_designer.team.edit_title') }}</h3>
            <div class="row-2">
                <div class="field"><label>{{ __('org_designer.team.name') }}</label><input type="text" id="teamName" /></div>
                <div class="field">
                    <label>{{ __('org_designer.team.topology') }}</label>
                    <select id="teamTopo">
                        <option value="stream-aligned">{{ __('org_designer.topology.stream-aligned') }}</option>
                        <option value="enabling">{{ __('org_designer.topology.enabling') }}</option>
                        <option value="platform">{{ __('org_designer.topology.platform') }}</option>
                        <option value="complicated-subsystem">{{ __('org_designer.topology.complicated-subsystem') }}</option>
                    </select>
                </div>
            </div>
            <div class="field"><label>{{ __('org_designer.team.product_1') }}</label><input type="text" id="teamP1" /></div>
            <div class="field"><label>{{ __('org_designer.team.product_2') }}</label><input type="text" id="teamP2" /></div>
            <div class="field"><label>{{ __('org_designer.team.product_3') }}</label><input type="text" id="teamP3" /></div>
            <div class="field"><label>{{ __('org_designer.team.notes') }}</label><textarea id="teamNotes"></textarea></div>
            <div class="actions">
                <button class="btn ghost" type="button" id="teamDelete" style="margin-right:auto;color:var(--hilti-red)">{{ __('org_designer.team.delete') }}</button>
                <button class="btn ghost" type="button" id="teamCancel">{{ __('common.actions.cancel') }}</button>
                <button class="btn" type="button" id="teamSave">{{ __('common.actions.save') }}</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="headModal">
        <div class="modal">
            <h3>{{ __('org_designer.head_modal.title') }}</h3>
            <div class="field"><label>{{ __('org_designer.card.role_type') }}</label><input type="text" id="headRole" list="roleList" placeholder="{{ __('org_designer.head_modal.role_placeholder') }}" /></div>
            <div class="row-3">
                <div class="field"><label>{{ __('org_designer.card.grade') }}</label><input type="text" id="headGrade" /></div>
                <div class="field">
                    <label>{{ __('org_designer.card.int_ext') }}</label>
                    <select id="headIntExt">
                        <option value="Internal">{{ __('org_designer.card.internal') }}</option>
                        <option value="External">{{ __('org_designer.card.external') }}</option>
                    </select>
                </div>
                <div class="field"><label>{{ __('org_designer.card.location') }}</label><input type="text" id="headLocation" list="locationList" /></div>
            </div>
            <div class="field"><label>{{ __('org_designer.card.notes') }}</label><textarea id="headNotes"></textarea></div>
            <div class="actions">
                <button class="btn ghost" type="button" id="headClear" style="margin-right:auto;color:var(--hilti-red)">{{ __('org_designer.head_modal.clear') }}</button>
                <button class="btn ghost" type="button" id="headCancel">{{ __('common.actions.cancel') }}</button>
                <button class="btn" type="button" id="headSave">{{ __('common.actions.save') }}</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="settingsModal">
        <div class="modal" style="min-width:560px">
            <h3>{{ __('org_designer.settings_modal.title') }}</h3>
            <div class="tabs">
                <div class="tab active" data-tab="general">{{ __('org_designer.settings_modal.tab_general') }}</div>
                <div class="tab" data-tab="roles">{{ __('org_designer.settings_modal.tab_roles') }}</div>
                <div class="tab" data-tab="locations">{{ __('org_designer.settings_modal.tab_locations') }}</div>
                <div class="tab" data-tab="topology">{{ __('org_designer.settings_modal.tab_topology') }}</div>
            </div>
            <div class="tab-pane" data-pane="general">
                <div class="field"><label>{{ __('org_designer.settings_modal.sheet_name') }}</label><input type="text" id="settingSheetName" /></div>
                <div class="field"><label>{{ __('org_designer.settings_modal.ilt_name') }}</label><input type="text" id="settingIltName" /></div>
                <div class="field"><label>{{ __('org_designer.settings_modal.col_width') }}</label><input type="number" id="settingColWidth" min="180" max="400" /></div>
            </div>
            <div class="tab-pane" data-pane="roles" style="display:none">
                <p style="font-size:12px;color:#666;margin:0 0 8px 0">{{ __('org_designer.settings_modal.roles_help') }}</p>
                <div id="roleConfigList"></div>
                <button class="add-mapping-btn" type="button" id="addRoleMapping">{{ __('org_designer.settings_modal.add_role') }}</button>
            </div>
            <div class="tab-pane" data-pane="locations" style="display:none">
                <p style="font-size:12px;color:#666;margin:0 0 8px 0">{{ __('org_designer.settings_modal.locations_help') }}</p>
                <div id="locationConfigList"></div>
                <button class="add-mapping-btn" type="button" id="addLocationMapping">{{ __('org_designer.settings_modal.add_location') }}</button>
            </div>
            <div class="tab-pane" data-pane="topology" style="display:none">
                <p style="font-size:12px;color:#666;margin:0 0 8px 0">{{ __('org_designer.settings_modal.topology_help') }}</p>
                <div id="topologyConfigList"></div>
            </div>
            <div class="actions">
                <button class="btn ghost" type="button" id="settingsCancel">{{ __('common.actions.cancel') }}</button>
                <button class="btn" type="button" id="settingsSave">{{ __('common.actions.save') }}</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="confirmModal">
        <div class="modal">
            <h3>{{ __('org_designer.confirm.title') }}</h3>
            <p id="confirmMessage" style="font-size:13px;color:#555"></p>
            <div class="actions">
                <button class="btn ghost" type="button" id="confirmCancel">{{ __('common.actions.cancel') }}</button>
                <button class="btn" type="button" id="confirmOk">{{ __('common.actions.confirm') }}</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="promptModal">
        <div class="modal">
            <h3 id="promptTitle" data-rename="{{ __('org_designer.rename.title') }}" data-new-area="{{ __('org_designer.rename.new_area_title') }}">{{ __('org_designer.rename.title') }}</h3>
            <div class="field">
                <input type="text" id="promptInput" placeholder="{{ __('org_designer.rename.placeholder') }}" />
            </div>
            <div class="actions">
                <button class="btn ghost" type="button" id="promptCancel">{{ __('common.actions.cancel') }}</button>
                <button class="btn" type="button" id="promptOk">{{ __('common.actions.save') }}</button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>
    @error('excelFile')
        <p class="sr-only">{{ $message }}</p>
    @enderror
</div>
