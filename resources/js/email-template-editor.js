import { CompositionMode, emailFramePolicy } from './email-template-content';

const starterHtml = '<table width="600" style="width:100%;max-width:600px;margin:0 auto;font-family:Arial,sans-serif"><tr><td style="padding:24px"><h1>Your update</h1><p>Hello {{name}},</p><p>Write your message here.</p></td></tr></table>';

async function initializeTemplateEditor(root) {
    if (root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    const source = root.querySelector('[data-template-source]');
    const format = root.querySelector('[data-body-format]');
    const status = root.querySelector('[data-editor-status]');
    const preview = root.querySelector('[data-template-preview]');
    const previewError = root.querySelector('[data-preview-error]');
    const blockSelection = root.querySelector('[data-block-selection]');
    const locked = root.dataset.locked === 'true';
    let editor = null;
    let editorPromise = null;
    let visualMode = false;
    let updating = false;
    let previewRequest = null;
    let timer = null;
    let destroyed = false;
    const modeState = new CompositionMode();
    const config = JSON.parse(root.querySelector('[data-editor-config]')?.textContent || '{}');
    const framePolicy = emailFramePolicy(config.imageHosts);

    const showError = (message) => {
        previewError.textContent = message;
        previewError.hidden = false;
        status.textContent = message;
    };

    const updateBlocks = () => {
        if (!editor) return;
        const previous = blockSelection.value;
        blockSelection.replaceChildren(new Option('Select a top-level block', ''));
        editor.getWrapper().components().forEach((component, index) => {
            const label = String(component.get('tagName') || component.get('type') || 'Block');
            blockSelection.add(new Option(`${index + 1}. ${label}`, String(index)));
        });
        if ([...blockSelection.options].some((option) => option.value === previous)) blockSelection.value = previous;
    };

    const syncVisual = () => {
        if (!editor || !visualMode || format.value !== 'html') return;
        const html = editor.runCommand('gjs-get-inlined-html');
        source.value = typeof html === 'string' ? html : editor.getHtml();
        root.querySelector('[data-template-design]').value = JSON.stringify(editor.getProjectData());
        updateBlocks();
    };

    const requestPreview = async ({ synchronize = true } = {}) => {
        if (synchronize) syncVisual();
        if (!source.value.trim()) {
            preview.srcdoc = '';
            return null;
        }
        previewRequest?.abort();
        const controller = new AbortController();
        previewRequest = controller;
        try {
            const response = await fetch(root.dataset.previewUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ body_format: format.value, html_body: source.value }),
                signal: controller.signal,
            });
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || 'Preview could not load. Check your message and try again.');
            if (destroyed || previewRequest !== controller) return null;
            // The scriptless preview is additionally constrained to configured image hosts.
            preview.srcdoc = `<!doctype html><html><head><meta charset="UTF-8"><meta http-equiv="Content-Security-Policy" content="${framePolicy}"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:16px;color:#202020;background:#fff">${result.html}</body></html>`;
            previewError.hidden = true;
            status.textContent = 'Sanitized preview updated with synthetic recipient details.';
            return result;
        } catch (error) {
            if (error.name !== 'AbortError' && !destroyed) showError(error.message || 'Preview could not load. Try again.');
            return null;
        }
    };

    const schedulePreview = () => {
        clearTimeout(timer);
        timer = setTimeout(() => requestPreview(), 900);
    };

    const ensureVisual = () => {
        if (editor || locked || destroyed) return Promise.resolve(Boolean(editor));
        if (editorPromise) return editorPromise;
        editorPromise = (async () => {
        status.textContent = 'Loading visual editor…';
        try {
            const [{ default: grapesjs }, { default: newsletter }] = await Promise.all([
                import('grapesjs'), import('grapesjs-preset-newsletter'), import('grapesjs/dist/css/grapes.min.css'), import('../css/email-template-editor.css'),
            ]);
            if (destroyed) return false;
            editor = grapesjs.init({
                container: root.querySelector('[data-editor-canvas]'),
                height: '560px',
                width: 'auto',
                storageManager: false,
                fromElement: false,
                noticeOnUnload: false,
                allowScripts: false,
                parser: { optionsHtml: { allowScripts: false, allowUnsafeAttr: false, allowUnsafeAttrValue: false } },
                assetManager: { upload: false, assets: [] },
                canvas: { scripts: [], styles: [], allowExternalDrop: false, frameContent: `<!doctype html><html><head><meta http-equiv="Content-Security-Policy" content="${framePolicy}"></head><body></body></html>` },
                plugins: [(instance) => newsletter(instance, { useCustomTheme: false, blocks: ['sect100', 'sect50', 'text', 'text-sect', 'button', 'divider', 'image'], showBlocksOnLoad: true, inlineCss: true, juiceOpts: { preserveMediaQueries: false } })],
            });
            // Stored project JSON is never executed or replayed. Rebuild only from server-sanitized HTML.
            editor.on('update', () => {
                if (!updating) { syncVisual(); schedulePreview(); }
            });
            editor.on('component:selected', (component) => {
                let top = component;
                while (top?.parent() && top.parent() !== editor.getWrapper()) top = top.parent();
                const index = editor.getWrapper().components().indexOf(top);
                blockSelection.value = index >= 0 ? String(index) : '';
            });
            return true;
        } catch (_) {
            showError('The visual editor could not load. You can continue in HTML source mode.');
            return false;
        }
        })();
        return editorPromise;
    };

    const chooseMode = async (mode) => {
        const nextVisual = mode === 'visual' && format.value === 'html' && !locked;
        if (!nextVisual) syncVisual();
        return modeState.select(nextVisual ? 'visual' : 'source', async () => {
            if (!(await ensureVisual())) return null;
            return requestPreview({ synchronize: false });
        }, (selected, clean) => {
            visualMode = selected === 'visual';
            if (visualMode) {
                updating = true;
                editor.setComponents(clean.source);
                updating = false;
                updateBlocks();
            }
            root.querySelector('[data-visual-panel]').hidden = !visualMode;
            root.querySelector('[data-source-panel]').hidden = visualMode;
            root.querySelector('[data-visual-tools]').hidden = format.value !== 'html';
            root.querySelector('[data-body-label]').textContent = format.value === 'text' ? 'Plain-text message' : 'HTML source';
            root.querySelectorAll('[data-mode]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.mode === (visualMode ? 'visual' : 'source'))));
            if (visualMode) editor.refresh();
        });
    };

    root.querySelectorAll('[data-mode]').forEach((button) => button.addEventListener('click', () => chooseMode(button.dataset.mode)));
    source.addEventListener('input', schedulePreview);
    source.addEventListener('invalid', () => chooseMode('source'));
    format.addEventListener('change', async () => {
        if (format.value === 'text') { visualMode = false; root.querySelector('[data-template-design]').value = ''; }
        const applied = await chooseMode(format.value === 'html' ? 'visual' : 'source');
        if (applied && format.value === 'text') requestPreview({ synchronize: false });
    });
    root.querySelector('[data-refresh-preview]').addEventListener('click', () => requestPreview());
    root.querySelectorAll('[data-preview-width]').forEach((button) => button.addEventListener('click', () => {
        preview.style.maxWidth = button.dataset.previewWidth === 'mobile' ? '375px' : '100%';
        root.querySelectorAll('[data-preview-width]').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
    }));
    blockSelection.addEventListener('change', () => {
        if (!editor || blockSelection.value === '') return;
        const component = editor.getWrapper().components().at(Number(blockSelection.value));
        if (component) editor.select(component);
    });
    root.querySelector('[data-add-block]').addEventListener('click', () => {
        if (!editor || locked) return;
        const blocks = {
            text: '<p style="padding:12px">Write your message here.</p>',
            heading: '<h2 style="padding:12px">Your heading</h2>',
            button: '<p style="padding:12px"><a href="https://example.com" style="background-color:#222222;color:#ffffff;padding:12px 20px">Read more</a></p>',
            divider: '<hr style="border-top:1px solid #cccccc">',
        };
        const added = editor.addComponents(blocks[root.querySelector('[data-block-type]').value]);
        if (added[0]) editor.select(added[0]);
        syncVisual();
        status.textContent = 'Block added. Use Selected block and Move up or Move down to reorder.';
    });
    root.querySelectorAll('[data-move]').forEach((button) => button.addEventListener('click', () => {
        if (!editor || locked || blockSelection.value === '') return;
        const collection = editor.getWrapper().components();
        const index = Number(blockSelection.value);
        const target = index + (button.dataset.move === 'up' ? -1 : 1);
        if (target < 0 || target >= collection.length) return;
        const component = collection.at(index);
        component.move(editor.getWrapper(), { at: target });
        updateBlocks();
        blockSelection.value = String(target);
        editor.select(component);
        syncVisual();
        status.textContent = `Block moved to position ${target + 1}.`;
    }));
    root.querySelector('[data-remove-block]').addEventListener('click', () => {
        if (!editor || locked || blockSelection.value === '') return;
        editor.getWrapper().components().at(Number(blockSelection.value))?.remove();
        syncVisual();
        status.textContent = 'Block removed.';
    });
    root.querySelector('[data-template-form]').addEventListener('submit', () => {
        syncVisual();
        status.textContent = 'Saving template…';
        root.querySelector('[data-template-save]').disabled = true;
    });
    root.__destroyTemplateEditor = () => {
        destroyed = true;
        modeState.dispose();
        clearTimeout(timer);
        previewRequest?.abort();
        editor?.destroy();
    };
    if (!source.value.trim() && format.value === 'html') source.value = starterHtml;
    const applied = await chooseMode(format.value === 'html' ? 'visual' : 'source');
    // Visual selection already requests a sanitized preview. A stale initialization
    // must not start another request that aborts the operator's newer selection.
    if (applied && format.value === 'text') await requestPreview();
}

export function initializeEmailTemplateEditors() {
    document.querySelectorAll('[data-email-template-editor]').forEach((root) => initializeTemplateEditor(root));
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeEmailTemplateEditors);
else initializeEmailTemplateEditors();
window.addEventListener('soft-navigate:after', initializeEmailTemplateEditors);
window.addEventListener('soft-navigate:before', () => document.querySelectorAll('[data-email-template-editor]').forEach((root) => root.__destroyTemplateEditor?.()));
