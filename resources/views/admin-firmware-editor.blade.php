<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Edit {{ $targetMeta['file_label'] }} - Admin Firmware</title>
    <style>
        :root {
            --bg: #111217;
            --bg-2: #181a20;
            --panel: #1f2430;
            --panel-2: #252b37;
            --line: #31394b;
            --text: #e4e8f1;
            --muted: #98a2b4;
            --accent: #60a5fa;
            --ok: #16a34a;
            --warn: #f59e0b;
            --danger: #ef4444;
            --sidebar: #16181f;
            --editor: #1e1e1e;
            --editor-tab: #2d2d30;
            --status: #007acc;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(180deg, #101217, #0b0d12 46%, #090b10);
            color: var(--text);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .editor-app {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 70px 1fr;
        }

        .activity-bar {
            background: linear-gradient(180deg, #13161d, #0f1218);
            border-right: 1px solid #1c2130;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 18px 0;
            gap: 16px;
        }

        .activity-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid #2a3142;
            background: rgba(96, 165, 250, .08);
            color: #93c5fd;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .workspace {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--line);
            background: rgba(17, 19, 24, .94);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .crumb {
            color: var(--muted);
            font-size: .84rem;
        }

        .title {
            min-width: 0;
        }

        .title h1 {
            margin: 0;
            font-size: 1.05rem;
            letter-spacing: .2px;
        }

        .title p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: .83rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px 14px;
            background: #1c2230;
            color: var(--text);
            text-decoration: none;
            cursor: pointer;
            font-weight: 700;
            font-size: .84rem;
        }

        .btn.secondary {
            background: #222937;
            color: #c7d2e3;
        }

        .btn.primary {
            border-color: rgba(22, 163, 74, .55);
            background: linear-gradient(180deg, #22c55e, #16a34a);
            color: #062a12;
        }

        .btn.primary:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .content {
            padding: 18px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 16px;
            min-width: 0;
            flex: 1;
        }

        .panel {
            background: linear-gradient(180deg, rgba(31, 36, 48, .96), rgba(23, 27, 35, .98));
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            min-width: 0;
        }

        .panel-head {
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            background: rgba(20, 24, 32, .94);
        }

        .panel-head h2 {
            margin: 0;
            font-size: .92rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #c7d2e3;
        }

        .panel-body {
            padding: 16px;
        }

        .meta-list {
            display: grid;
            gap: 12px;
        }

        .meta-item span {
            display: block;
        }

        .meta-item span:first-child {
            font-size: .73rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 4px;
        }

        .meta-item code,
        .meta-item strong {
            color: var(--text);
            font-size: .84rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: .76rem;
            font-weight: 700;
            border: 1px solid rgba(96, 165, 250, .35);
            background: rgba(96, 165, 250, .12);
            color: #bfdbfe;
        }

        .badge.warn {
            border-color: rgba(245, 158, 11, .35);
            background: rgba(245, 158, 11, .12);
            color: #fcd34d;
        }

        .flash {
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
            font-size: .85rem;
        }

        .flash.ok {
            border: 1px solid rgba(22, 163, 74, .45);
            background: rgba(22, 163, 74, .12);
            color: #bbf7d0;
        }

        .flash.err {
            border: 1px solid rgba(239, 68, 68, .45);
            background: rgba(239, 68, 68, .12);
            color: #fecaca;
        }

        .editor-shell {
            display: flex;
            flex-direction: column;
            min-height: 76vh;
            background: var(--editor);
        }

        .editor-tabs {
            display: flex;
            align-items: center;
            gap: 0;
            border-bottom: 1px solid #30343f;
            background: var(--editor-tab);
            min-height: 42px;
        }

        .editor-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 16px;
            border-right: 1px solid #3b3f4a;
            font-size: .83rem;
            color: #d4d4d4;
            background: #1e1e1e;
        }

        .editor-stage {
            position: relative;
            flex: 1;
            min-height: 620px;
        }

        #monaco-editor,
        #editor-fallback {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
            margin: 0;
        }

        #editor-fallback {
            resize: none;
            padding: 18px;
            background: #1e1e1e;
            color: #d4d4d4;
            font: 14px/1.55 Consolas, "Courier New", monospace;
            outline: none;
        }

        .statusbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 12px;
            background: var(--status);
            color: #f8fbff;
            font-size: .78rem;
        }

        .statusbar code {
            color: inherit;
            font-size: inherit;
        }

        .hint {
            color: var(--muted);
            font-size: .82rem;
            line-height: 1.5;
        }

        .hint code {
            color: #dbeafe;
        }

        @media (max-width: 1100px) {
            .content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 820px) {
            .editor-app {
                grid-template-columns: 1fr;
            }

            .activity-bar {
                display: none;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .topbar-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="editor-app">
        <aside class="activity-bar" aria-hidden="true">
            <div class="activity-icon">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
                    <path d="M4 20h4l10-10-4-4L4 16v4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="m12 6 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="activity-icon">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
                    <path d="M5 6h14M5 12h14M5 18h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </div>
        </aside>

        <main class="workspace">
            <header class="topbar">
                <div class="topbar-left">
                    <a class="btn secondary" href="{{ route('admin.config.index', ['device_id' => $device->id], false) }}">Back to Admin</a>
                    <div class="title">
                        <div class="crumb">Admin Config / Firmware Editor</div>
                        <h1>{{ $targetMeta['file_label'] }}</h1>
                        <p>{{ $device->nama_device }} | Device ID {{ $device->id }} | {{ $targetMeta['description'] }}</p>
                    </div>
                </div>
                <div class="topbar-actions">
                    <button type="button" class="btn secondary" id="load-standard-btn">Load Generated Standard</button>
                    <button type="submit" form="firmware-editor-form" class="btn primary" id="save-editor-btn">Save</button>
                </div>
            </header>

            <div class="content">
                <section class="panel">
                    <div class="panel-head">
                        <h2>Editor Context</h2>
                    </div>
                    <div class="panel-body">
                        @if (session('admin_status'))
                            <div class="flash ok">{{ session('admin_status') }}</div>
                        @endif

                        @if ($errors->any())
                            <div class="flash err">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div class="meta-list">
                            <div class="meta-item">
                                <span>Device</span>
                                <strong>{{ $device->nama_device }}</strong>
                            </div>
                            <div class="meta-item">
                                <span>File</span>
                                <code>{{ $targetMeta['file_label'] }}</code>
                            </div>
                            <div class="meta-item">
                                <span>Source Mode</span>
                                <span class="badge {{ $isCustomOverride ? 'warn' : '' }}">
                                    {{ $isCustomOverride ? 'Custom Override Active' : 'Generated Standard Active' }}
                                </span>
                            </div>
                            <div class="meta-item">
                                <span>Workspace Target</span>
                                <code>{{ $workspacePaths[$targetMeta['bundle_key']] ?? '-' }}</code>
                            </div>
                            <div class="meta-item">
                                <span>Behavior</span>
                                <span class="hint">
                                    Save stores this file only for the selected device profile. After save, workspace firmware is synced immediately.
                                </span>
                            </div>
                            <div class="meta-item">
                                <span>Revert Strategy</span>
                                <span class="hint">
                                    Use <code>Load Generated Standard</code>, then save. If editor content matches the generated standard, custom override is cleared.
                                </span>
                            </div>
                            <div class="meta-item">
                                <span>Shortcut</span>
                                <span class="hint">
                                    Press <code>Ctrl+S</code> or <code>Cmd+S</code> to save.
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <form id="firmware-editor-form" method="POST" action="{{ route('admin.config.devices.firmware.editor.save', ['device' => $device->id, 'target' => $targetMeta['target']], false) }}">
                        @csrf
                        <textarea id="editor-content" name="content" hidden></textarea>
                    </form>

                    <div class="editor-shell">
                        <div class="editor-tabs">
                            <div class="editor-tab">{{ $targetMeta['file_label'] }}</div>
                        </div>
                        <div class="editor-stage">
                            <div id="monaco-editor" aria-hidden="true"></div>
                            <textarea id="editor-fallback" spellcheck="false">{{ $content }}</textarea>
                        </div>
                        <div class="statusbar">
                            <div id="editor-status-left">Ready</div>
                            <div id="editor-status-right">{{ strtoupper($targetMeta['language']) }} | Lines: 0</div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <textarea id="generated-standard-content" hidden>{{ $standardContent }}</textarea>

    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/loader.js"></script>
    <script>
        (function () {
            const form = document.getElementById('firmware-editor-form');
            const saveButton = document.getElementById('save-editor-btn');
            const fallback = document.getElementById('editor-fallback');
            const hiddenContent = document.getElementById('editor-content');
            const generatedStandard = document.getElementById('generated-standard-content');
            const statusLeft = document.getElementById('editor-status-left');
            const statusRight = document.getElementById('editor-status-right');
            const loadStandardButton = document.getElementById('load-standard-btn');
            const language = @json($targetMeta['language']);
            const fileLabel = @json($targetMeta['file_label']);
            const standardContent = generatedStandard ? generatedStandard.value : '';

            let monacoEditor = null;
            let isDirty = false;
            let initialContent = fallback.value || '';
            let monacoReady = false;

            function getEditorValue() {
                return monacoEditor ? monacoEditor.getValue() : fallback.value;
            }

            function setEditorValue(value) {
                if (monacoEditor) {
                    monacoEditor.setValue(value);
                    return;
                }

                fallback.value = value;
            }

            function updateStatusBar() {
                const value = getEditorValue();
                const lineCount = value === '' ? 1 : value.split(/\r\n|\r|\n/).length;
                statusLeft.textContent = isDirty ? 'Unsaved changes' : 'Saved state';
                statusRight.textContent = language.toUpperCase() + ' | Lines: ' + lineCount + (monacoReady ? ' | Monaco' : ' | Fallback');
                saveButton.disabled = !isDirty;
            }

            function refreshDirtyState() {
                isDirty = getEditorValue() !== initialContent;
                updateStatusBar();
            }

            function syncFormContent() {
                hiddenContent.value = getEditorValue();
            }

            fallback.addEventListener('input', refreshDirtyState);

            loadStandardButton.addEventListener('click', function () {
                const confirmed = window.confirm(
                    'Load generated standard ' + fileLabel + '? Save afterward to clear the custom override for this device.'
                );

                if (!confirmed) {
                    return;
                }

                setEditorValue(standardContent);
                refreshDirtyState();
            });

            form.addEventListener('submit', function () {
                syncFormContent();
                saveButton.disabled = true;
            });

            window.addEventListener('beforeunload', function (event) {
                if (!isDirty) {
                    return;
                }

                event.preventDefault();
                event.returnValue = '';
            });

            window.addEventListener('keydown', function (event) {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                    event.preventDefault();
                    syncFormContent();
                    form.requestSubmit();
                }
            });

            updateStatusBar();

            window.MonacoEnvironment = {
                getWorkerUrl: function () {
                    const workerSource = [
                        "self.MonacoEnvironment = { baseUrl: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/' };",
                        "importScripts('https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/base/worker/workerMain.js');",
                    ].join('');

                    return URL.createObjectURL(new Blob([workerSource], { type: 'text/javascript' }));
                },
            };

            if (!window.require || !document.getElementById('monaco-editor')) {
                return;
            }

            window.require.config({
                paths: {
                    vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs',
                },
            });

            window.require(['vs/editor/editor.main'], function () {
                const mountNode = document.getElementById('monaco-editor');
                monacoEditor = monaco.editor.create(mountNode, {
                    value: fallback.value || '',
                    language: language,
                    theme: 'vs-dark',
                    automaticLayout: true,
                    fontSize: 14,
                    fontFamily: 'Consolas, "Courier New", monospace',
                    minimap: { enabled: true },
                    wordWrap: 'off',
                    scrollBeyondLastLine: false,
                    roundedSelection: false,
                    smoothScrolling: true,
                });

                fallback.style.display = 'none';
                monacoReady = true;
                monacoEditor.onDidChangeModelContent(refreshDirtyState);
                refreshDirtyState();
            }, function () {
                monacoReady = false;
                updateStatusBar();
            });
        })();
    </script>
</body>
</html>
