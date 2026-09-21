(() => {
    const openModal = (id) => {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        modal.querySelector('input, textarea, button')?.focus();
    };

    const closeModal = (modal) => {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    };

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-open-modal]');
        if (opener) openModal(opener.dataset.openModal);

        const closer = event.target.closest('[data-close-modal]');
        if (closer) closeModal(closer.closest('.modal-backdrop'));
        if (event.target.classList.contains('modal-backdrop')) closeModal(event.target);

        const viewport = event.target.closest('[data-viewport]');
        if (viewport) {
            document.querySelectorAll('[data-viewport]').forEach((button) => button.classList.remove('is-active'));
            viewport.classList.add('is-active');
            const canvas = document.querySelector('[data-desktop-canvas]');
            const responsive = document.querySelector('[data-responsive-canvas]');
            const sizeLabel = document.querySelector('[data-canvas-size]');
            const mode = viewport.dataset.viewport;
            if (mode === 'desktop') {
                canvas?.classList.remove('is-preview-hidden');
                responsive?.classList.remove('is-visible');
            } else {
                canvas?.classList.add('is-preview-hidden');
                if (responsive) {
                    responsive.classList.add('is-visible');
                    responsive.dataset.previewSize = mode;
                }
            }
            if (sizeLabel) {
                const labels = { desktop: 'Desktop · fluido', tablet: 'Tablet · 768px', mobile: 'Mobile · 390px' };
                sizeLabel.textContent = labels[mode] || '';
            }
        }
    });

    document.addEventListener('input', (event) => {
        const search = event.target.closest('[data-component-search]');
        if (!search) return;
        if (search.closest('[data-library-browser]')) return;
        const value = search.value.trim().toLocaleLowerCase('es');
        document.querySelectorAll('[data-component-card]').forEach((card) => {
            const haystack = (card.dataset.search || '').toLocaleLowerCase('es');
            card.hidden = value !== '' && !haystack.includes(value);
        });
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-confirm]');
        if (form && !window.confirm(form.dataset.confirm || '¿Continuar?')) {
            event.preventDefault();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.is-open').forEach(closeModal);
        }
    });

    const dismissToast = (toast) => {
        if (!toast || toast.dataset.toastLeaving === '1') return;
        toast.dataset.toastLeaving = '1';
        toast.classList.add('is-leaving');
        window.setTimeout(() => toast.remove(), 280);
    };

    const initToast = (toast) => {
        if (!toast || toast.dataset.toastReady === '1') return;
        toast.dataset.toastReady = '1';
        toast.setAttribute('role', toast.classList.contains('toast-error') || toast.classList.contains('toast-danger') ? 'alert' : 'status');
        toast.setAttribute('aria-live', 'polite');

        if (!toast.querySelector('[data-toast-close]')) {
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'toast-close';
            close.dataset.toastClose = '1';
            close.setAttribute('aria-label', 'Cerrar notificación');
            close.textContent = '×';
            toast.appendChild(close);
        }

        toast.querySelector('[data-toast-close]')?.addEventListener('click', () => dismissToast(toast));
        window.setTimeout(() => dismissToast(toast), 5000);
    };

    document.querySelectorAll('.toast').forEach(initToast);

    // Some legacy views can render a flash message after app.js has loaded.
    // Observe the document so those notifications get the same 5 s lifecycle.
    const toastObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
            if (!(node instanceof Element)) return;
            if (node.matches('.toast')) initToast(node);
            node.querySelectorAll?.('.toast').forEach(initToast);
        }));
    });
    toastObserver.observe(document.documentElement, { childList: true, subtree: true });
})();

(() => {
    const root = document.querySelector('[data-design-root]');
    if (!root) return;

    const form = document.querySelector('[data-design-form]');
    const preview = document.querySelector('[data-brand-preview]');
    const safeHex = (value) => /^#[0-9a-f]{6}$/i.test(value || '') ? value.toUpperCase() : null;

    const syncPreview = () => {
        if (!form || !preview) return;
        const map = {
            primary_color: '--p', secondary_color: '--s', accent_color: '--a', background_color: '--bg',
            surface_color: '--surface', text_color: '--text', muted_color: '--muted', border_radius: '--radius'
        };
        Object.entries(map).forEach(([name, cssVar]) => {
            const field = form.elements[name];
            if (!field) return;
            const value = name.endsWith('_color') ? safeHex(field.value) : field.value;
            if (value) preview.style.setProperty(cssVar, value);
        });
        const heading = form.elements.heading_font?.value || 'Inter';
        const body = form.elements.body_font?.value || 'Inter';
        preview.style.fontFamily = `'${body}', sans-serif`;
        const headingPreview = document.querySelector('[data-preview-heading]');
        if (headingPreview) headingPreview.style.fontFamily = `'${heading}', sans-serif`;
        document.querySelector('[data-summary-heading]')?.replaceChildren(document.createTextNode(heading));
        document.querySelector('[data-summary-body]')?.replaceChildren(document.createTextNode(body));
        const primary = safeHex(form.elements.primary_color?.value);
        if (primary) {
            const summary = document.querySelector('[data-summary-primary]');
            if (summary) summary.textContent = primary;
            const dot = summary?.previousElementSibling;
            if (dot) dot.style.background = primary;
        }
        document.querySelectorAll('[data-font-preview]').forEach((el) => {
            const key = el.dataset.fontPreview === 'heading' ? heading : body;
            el.style.fontFamily = `'${key}', sans-serif`;
        });
    };

    document.querySelectorAll('[data-color-picker]').forEach((picker) => {
        picker.addEventListener('input', () => {
            const text = document.querySelector(`[data-color-text="${picker.dataset.colorPicker}"]`);
            if (text) text.value = picker.value.toUpperCase();
            syncPreview();
        });
    });
    document.querySelectorAll('[data-color-text]').forEach((input) => {
        input.addEventListener('input', () => {
            const value = safeHex(input.value);
            const picker = document.querySelector(`[data-color-picker="${input.dataset.colorText}"]`);
            if (value && picker) picker.value = value;
            syncPreview();
        });
    });
    form?.addEventListener('input', syncPreview);

    const syncSourceFields = () => {
        document.querySelectorAll('[data-font-source]').forEach((select) => {
            const field = document.querySelector(`[data-google-field="${select.dataset.fontSource}"]`);
            field?.classList.toggle('is-hidden', select.value !== 'google');
        });
    };
    document.querySelectorAll('[data-font-source]').forEach((select) => select.addEventListener('change', syncSourceFields));
    syncSourceFields();
    syncPreview();

    document.querySelectorAll('[data-auto-submit]').forEach((input) => {
        input.addEventListener('change', () => { if (input.files?.length) input.form?.submit(); });
    });

    const extractor = document.querySelector('[data-extract-palette]');
    extractor?.addEventListener('click', () => {
        const img = document.querySelector('[data-palette-source] img');
        const target = document.querySelector('[data-detected-palette]');
        if (!img || !target) return;
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            const size = 96;
            canvas.width = size; canvas.height = size;
            ctx.drawImage(img, 0, 0, size, size);
            const data = ctx.getImageData(0, 0, size, size).data;
            const buckets = new Map();
            for (let i = 0; i < data.length; i += 16) {
                const a = data[i + 3]; if (a < 180) continue;
                let r = data[i], g = data[i+1], b = data[i+2];
                const max = Math.max(r,g,b), min = Math.min(r,g,b);
                if (max > 245 && min > 238) continue;
                if (max < 18) continue;
                r = Math.round(r/32)*32; g = Math.round(g/32)*32; b = Math.round(b/32)*32;
                r = Math.min(255,r); g = Math.min(255,g); b = Math.min(255,b);
                const key = `${r},${g},${b}`;
                buckets.set(key, (buckets.get(key) || 0) + 1);
            }
            const colors = [...buckets.entries()].sort((a,b)=>b[1]-a[1]).map(([key])=>key.split(',').map(Number)).filter((rgb,index,list)=>{
                return list.slice(0,index).every(prev => Math.sqrt(prev.reduce((sum,v,j)=>sum + Math.pow(v-rgb[j],2),0)) > 70);
            }).slice(0,6).map(rgb => '#' + rgb.map(v=>v.toString(16).padStart(2,'0')).join('').toUpperCase());
            target.innerHTML = '';
            colors.forEach((color, index) => {
                const btn = document.createElement('button'); btn.type='button'; btn.style.background=color; btn.title=color;
                btn.addEventListener('click', () => {
                    const roles = ['primary_color','secondary_color','accent_color'];
                    const role = roles[Math.min(index,2)];
                    const text = document.querySelector(`[data-color-text="${role}"]`), picker = document.querySelector(`[data-color-picker="${role}"]`);
                    if (text) text.value=color; if (picker) picker.value=color; syncPreview();
                });
                target.appendChild(btn);
            });
            if (colors.length >= 1) {
                const roles = ['primary_color','secondary_color','accent_color'];
                colors.slice(0,3).forEach((color,index)=>{
                    const role=roles[index], text=document.querySelector(`[data-color-text="${role}"]`), picker=document.querySelector(`[data-color-picker="${role}"]`);
                    if(text) text.value=color; if(picker) picker.value=color;
                });
                syncPreview();
            }
        } catch (error) {
            console.error('No se pudo extraer la paleta', error);
        }
    });
})();

(() => {
    document.querySelectorAll('[data-inspector-tabs]').forEach((tabs) => {
        const buttons = tabs.querySelectorAll('[data-inspector-tab]');
        const scope = tabs.parentElement;
        if (!scope) return;
        const panels = scope.querySelectorAll('[data-inspector-panel]');
        const pageId = tabs.dataset.pageId || '0';
        const instanceId = tabs.dataset.instanceId || '0';
        const storageKey = `blumi.builder.inspectorTab.${pageId}.${instanceId}`;

        const activate = (target, persist = true) => {
            if (!['content', 'style'].includes(target)) target = 'content';
            const targetButton = Array.from(buttons).find((button) => button.dataset.inspectorTab === target);
            if (!targetButton || targetButton.hidden) target = 'content';
            buttons.forEach((button) => {
                const active = button.dataset.inspectorTab === target;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panels.forEach((panel) => { panel.hidden = panel.dataset.inspectorPanel !== target; });
            if (persist) {
                try { localStorage.setItem(storageKey, target); } catch (_) {}
            }
        };

        let initial = 'content';
        try { initial = localStorage.getItem(storageKey) || 'content'; } catch (_) {}
        activate(initial, false);

        buttons.forEach((button) => {
            button.addEventListener('click', () => activate(button.dataset.inspectorTab || 'content'));
        });

        scope.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', () => {
                const active = Array.from(buttons).find((button) => button.classList.contains('is-active'));
                if (active) {
                    try { localStorage.setItem(storageKey, active.dataset.inspectorTab || 'content'); } catch (_) {}
                }
            });
        });
    });

    document.querySelectorAll('.palette-token input[type="radio"]').forEach((input) => {
        input.addEventListener('change', () => {
            const grid = input.closest('.palette-token-grid');
            grid?.querySelectorAll('.palette-token').forEach((token) => token.classList.remove('is-selected'));
            const token = input.closest('.palette-token');
            token?.classList.add('is-selected');
            const field = input.closest('.style-control-field');
            const status = field?.querySelector('[data-style-selection-status] strong');
            if (status && token) status.textContent = token.dataset.tokenLabel || input.value;
        });
    });
})();


(() => {
    const shell = document.querySelector('.builder-shell');
    if (!shell) return;
    const key = 'blumi.builder.leftCollapsed';
    const apply = (collapsed) => {
        shell.classList.toggle('is-left-collapsed', collapsed);
        document.querySelectorAll('[data-builder-left-toggle]').forEach((button) => {
            const isRestore = button.classList.contains('builder-left-restore');
            button.setAttribute('aria-label', collapsed ? 'Mostrar panel izquierdo' : 'Ocultar panel izquierdo');
            button.title = collapsed ? 'Mostrar panel izquierdo' : 'Ocultar panel izquierdo';
            if (!isRestore) button.textContent = collapsed ? '›' : '‹';
        });
    };
    let collapsed = false;
    try { collapsed = localStorage.getItem(key) === '1'; } catch (_) {}
    apply(collapsed);
    document.querySelectorAll('[data-builder-left-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            collapsed = !shell.classList.contains('is-left-collapsed');
            apply(collapsed);
            try { localStorage.setItem(key, collapsed ? '1' : '0'); } catch (_) {}
        });
    });
})();

(() => {
    const syncBackgroundMode = () => {
        document.querySelectorAll('form[action*="components.style"]').forEach((form) => {
            const selected = form.querySelector('input[name="background_mode"]:checked');
            const control = form.querySelector('[data-background-image-control]');
            if (!selected || !control) return;
            control.hidden = selected.value !== 'image';
            form.querySelectorAll('.background-mode-option').forEach((option) => {
                const input = option.querySelector('input[name="background_mode"]');
                option.classList.toggle('is-selected', input?.checked === true);
            });
        });
    };

    document.querySelectorAll('input[name="background_mode"]').forEach((input) => {
        input.addEventListener('change', syncBackgroundMode);
    });
    syncBackgroundMode();

    document.querySelectorAll('.visibility-control-row input[type="checkbox"]').forEach((input) => {
        input.addEventListener('change', () => {
            const row = input.closest('.visibility-control-row');
            const status = row?.querySelector('small');
            row?.classList.toggle('is-hidden-choice', input.checked);
            if (status) status.textContent = input.checked ? 'Oculto' : 'Visible';
        });
        input.dispatchEvent(new Event('change'));
    });
})();


(() => {
    const canvas = document.querySelector('.canvas-stage');
    const inspector = document.querySelector('.builder-inspector');
    const tabs = document.querySelector('[data-inspector-tabs]');
    if (!canvas || !tabs) return;
    const pageId = tabs.dataset.pageId || '0';
    const instanceId = tabs.dataset.instanceId || '0';
    const key = `blumi.builder.position.${pageId}.${instanceId}`;

    const savePosition = () => {
        try {
            sessionStorage.setItem(key, JSON.stringify({
                canvasTop: canvas.scrollTop,
                canvasLeft: canvas.scrollLeft,
                inspectorTop: inspector ? inspector.scrollTop : 0
            }));
        } catch (_) {}
    };

    document.querySelectorAll('form[action*="components.update"],form[action*="components.style"],form[action*="components.move"]').forEach((form) => {
        form.addEventListener('submit', savePosition);
    });

    let stored = null;
    try { stored = JSON.parse(sessionStorage.getItem(key) || 'null'); } catch (_) {}
    if (stored) {
        requestAnimationFrame(() => {
            canvas.scrollTop = Number(stored.canvasTop) || 0;
            canvas.scrollLeft = Number(stored.canvasLeft) || 0;
            if (inspector) inspector.scrollTop = Number(stored.inspectorTop) || 0;
            try { sessionStorage.removeItem(key); } catch (_) {}
        });
    } else if (location.hash === `#blumi-instance-${instanceId}`) {
        requestAnimationFrame(() => document.getElementById(`blumi-instance-${instanceId}`)?.scrollIntoView({block:'center'}));
    }
})();


(() => {
    const frame = document.querySelector('[data-responsive-canvas]');
    if (!frame) return;
    window.addEventListener('message', (event) => {
        if (event.source !== frame.contentWindow || !event.data) return;
        if (event.data.type === 'blumi-preview-height') {
            const h = Math.max(520, Math.min(12000, Number(event.data.height) || 0));
            frame.style.height = h + 'px';
        }
        if (event.data.type === 'blumi-select-instance' && event.data.id) {
            const url = new URL(window.location.href);
            url.searchParams.set('instance_id', String(event.data.id));
            url.hash = `blumi-instance-${event.data.id}`;
            window.location.href = url.toString();
        }
    });
})();



/* v0.7.2.2 · Preserve canvas position when selecting in-canvas and focus target from left nav. */
(() => {
    const canvas = document.querySelector('.canvas-stage');
    const tabs = document.querySelector('[data-inspector-tabs]');
    if (!canvas || !tabs) return;

    const pageId = tabs.dataset.pageId || '0';
    const makeKey = (instanceId) => `blumi.builder.position.${pageId}.${instanceId}`;

    const saveForTarget = (instanceId) => {
        if (!instanceId) return;
        const inspector = document.querySelector('.builder-inspector');
        try {
            sessionStorage.setItem(makeKey(instanceId), JSON.stringify({
                canvasTop: canvas.scrollTop,
                canvasLeft: canvas.scrollLeft,
                inspectorTop: inspector ? inspector.scrollTop : 0
            }));
        } catch (_) {}
    };

    // Clicking directly on the component should keep the exact viewport position.
    document.querySelectorAll('[data-builder-canvas-select]').forEach((link) => {
        link.addEventListener('click', () => saveForTarget(link.dataset.instanceId));
    });

    // Left navigation means "take me to this section". Do not reuse another section's
    // saved position; let the target hash center the selected component after reload.
    document.querySelectorAll('[data-builder-section-link]').forEach((link) => {
        link.addEventListener('click', () => {
            const targetId = link.dataset.instanceId || '';
            try { sessionStorage.removeItem(makeKey(targetId)); } catch (_) {}
        });
    });
})();

/* v0.7.2 · Keep builder scroll containers bounded after inspector changes. */
(() => {
    const shell = document.querySelector('.builder-shell');
    if (!shell) return;

    document.documentElement.classList.add('blumi-builder-active');
    document.body.classList.add('blumi-builder-active');

    const containers = [
        document.querySelector('.builder-inspector'),
        document.querySelector('.builder-left'),
        document.querySelector('.canvas-stage')
    ].filter(Boolean);

    const clampScroll = (container) => {
        const maxTop = Math.max(0, container.scrollHeight - container.clientHeight);
        const maxLeft = Math.max(0, container.scrollWidth - container.clientWidth);
        if (container.scrollTop > maxTop) container.scrollTop = maxTop;
        if (container.scrollLeft > maxLeft) container.scrollLeft = maxLeft;
        if (container.scrollTop < 0) container.scrollTop = 0;
        if (container.scrollLeft < 0) container.scrollLeft = 0;
    };

    let raf = 0;
    const normalize = () => {
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(() => {
            containers.forEach(clampScroll);
            // Run once more after fonts/images/layout have had a frame to settle.
            requestAnimationFrame(() => containers.forEach(clampScroll));
        });
    };

    const inspector = document.querySelector('.builder-inspector');
    if (inspector) {
        const resizeObserver = typeof ResizeObserver !== 'undefined'
            ? new ResizeObserver(normalize)
            : null;
        resizeObserver?.observe(inspector);
        inspector.querySelectorAll('[data-inspector-panel], .inspector-section, .inspector-form').forEach((node) => {
            resizeObserver?.observe(node);
        });

        const mutationObserver = new MutationObserver(normalize);
        mutationObserver.observe(inspector, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['hidden', 'class', 'style']
        });

        inspector.addEventListener('click', normalize, true);
        inspector.addEventListener('change', normalize, true);
    }

    window.addEventListener('resize', normalize);
    window.addEventListener('pageshow', normalize);
    normalize();
})();


/* Blumi 1.2.3 · Smart Links + content persistence regression repair */
(() => {
    const refreshSmartLink = (root) => {
        const typeSelect = root.querySelector('[data-smart-link-type]');
        if (!typeSelect) return;
        const type = typeSelect.value || 'none';

        root.querySelectorAll('[data-smart-link-panel]').forEach((panel) => {
            const active = panel.dataset.smartLinkPanel === type;
            panel.hidden = !active;
            // Hidden controls still submit in HTML forms. Disable inactive modes so
            // repeated names (value/page_id/section_id) cannot overwrite the active one.
            panel.querySelectorAll('input,select,textarea').forEach((control) => {
                control.disabled = !active;
            });
        });

        if (type === 'page_section') {
            const pageSelect = root.querySelector('[data-smart-link-page]');
            const sectionSelect = root.querySelector('[data-smart-link-section]');
            if (pageSelect && sectionSelect) {
                const pageId = pageSelect.value;
                let firstVisible = null;
                [...sectionSelect.options].forEach((option, index) => {
                    if (index === 0) { option.hidden = false; return; }
                    const visible = option.dataset.pageId === pageId;
                    option.hidden = !visible;
                    if (visible && !firstVisible) firstVisible = option;
                });
                const selected = sectionSelect.selectedOptions[0];
                if (selected && selected.dataset.pageId && selected.dataset.pageId !== pageId) {
                    sectionSelect.value = firstVisible ? firstVisible.value : '0';
                }
            }
        }
    };

    document.querySelectorAll('[data-smart-link-field]').forEach((root) => {
        const type = root.querySelector('[data-smart-link-type]');
        const page = root.querySelector('[data-smart-link-page]');
        type?.addEventListener('change', () => refreshSmartLink(root));
        page?.addEventListener('change', () => refreshSmartLink(root));
        refreshSmartLink(root);
    });
})();

/* Blumi 1.3.3 · Personalizar con Blumi · Propuesta + preview + aplicar */
(() => {
    const panel = document.querySelector('[data-personalize-panel]');
    if (!panel || panel.classList.contains('is-locked')) return;

    const instanceId = String(panel.dataset.instanceId || '');
    if (!instanceId) return;

    let allowedTargets = [];
    try { allowedTargets = JSON.parse(panel.dataset.availableTargets || '[]'); } catch (_) { allowedTargets = []; }
    const allowed = new Set(Array.isArray(allowedTargets) ? allowedTargets : []);
    if (!allowed.size) return;

    const section = document.querySelector(`.canvas-component[data-instance-id="${CSS.escape(instanceId)}"]`);
    const stage = section?.querySelector('.bl-component-stage');
    const toggle = panel.querySelector('[data-personalize-toggle]');
    const selectionRoot = panel.querySelector('[data-personalize-selection]');
    const draft = panel.querySelector('[data-personalize-draft]');
    const hint = panel.querySelector('[data-personalize-hint]');
    const generate = panel.querySelector('[data-personalize-generate]');
    const proposalBox = panel.querySelector('[data-personalize-proposal]');
    const summary = panel.querySelector('[data-personalize-summary]');
    const willChange = panel.querySelector('[data-personalize-will-change]');
    const willKeep = panel.querySelector('[data-personalize-will-keep]');
    const discard = panel.querySelector('[data-personalize-discard]');
    const apply = panel.querySelector('[data-personalize-apply]');
    const variantBox = panel.querySelector('[data-personalize-variant]');
    const variantReason = panel.querySelector('[data-personalize-variant-reason]');
    const loader = document.querySelector('[data-personalize-loader]');
    const loaderTitle = loader?.querySelector('[data-personalize-loader-title]');
    const loaderMessage = loader?.querySelector('[data-personalize-loader-message]');
    const csrf = String(panel.dataset.csrf || '');
    if (!section || !stage || !toggle || !selectionRoot || !draft || !generate) return;

    const storageKey = `blumi.personalize.selection.${instanceId}`;
    const draftKey = `blumi.personalize.draft.${instanceId}`;
    const selected = new Map();
    let active = false;
    let hovered = null;
    let draftTouched = false;
    let pendingProposal = null;
    let previewStyle = null;

    const semanticLabels = {
        'headline':'Título principal','title':'Título','description':'Descripción','subtitle':'Subtítulo',
        'primary-cta':'CTA principal','secondary-cta':'CTA secundario','image-primary':'Imagen principal',
        'image-secondary':'Imagen secundaria','badge':'Sello / badge','cards':'Grupo de tarjetas','card':'Tarjeta',
        'logo':'Logo','nav-links':'Enlaces de navegación','eyebrow':'Etiqueta superior','copy':'Texto','media':'Media'
    };

    const humanize = (value) => {
        const clean = String(value || '').replace(/^(?:id:|class:)/, '');
        if (semanticLabels[clean]) return semanticLabels[clean];

        // Componentes legacy generados por Blumi usan BEM, por ejemplo:
        // bl-generated-photo-hero__title -> Titulo.
        // Mostramos al disenador el rol del elemento, no el nombre tecnico completo.
        let friendly = clean;
        if (friendly.startsWith('bl-generated-') && friendly.includes('__')) {
            friendly = friendly.split('__').pop() || friendly;
        }
        friendly = friendly.replace(/--[A-Za-z0-9_-]+$/, '');
        const normalized = friendly.toLowerCase();
        const legacyLabels = {
            'title':'Titulo','headline':'Titulo principal','description':'Descripcion','subtitle':'Subtitulo',
            'eyebrow':'Etiqueta superior','content':'Contenido','copy':'Texto','text':'Texto',
            'image':'Imagen','visual':'Imagen / visual','media':'Media','inner':'Contenedor interno',
            'panel':'Panel','actions':'Acciones','cta':'CTA','button':'Boton','badge':'Sello / badge',
            'cards':'Grupo de tarjetas','card':'Tarjeta','grid':'Cuadricula','list':'Lista','item':'Elemento',
            'overlay':'Capa visual','shell':'Contenedor','header':'Cabecera','footer':'Pie'
        };
        if (legacyLabels[normalized]) return legacyLabels[normalized];
        return friendly.replace(/[_-]+/g, ' ').replace(/\b\w/g, (m) => m.toUpperCase()).trim() || 'Elemento';
    };

    const targetForNode = (node) => {
        let current = node instanceof Element ? node : null;
        while (current && current !== stage) {
            const semantic = (current.getAttribute('data-blumi-element') || '').trim();
            if (semantic && allowed.has(semantic)) return { target: semantic, node: current, label: humanize(semantic) };
            if (current.id && allowed.has(`id:${current.id}`)) return { target: `id:${current.id}`, node: current, label: humanize(current.id) };
            for (const cls of current.classList) {
                const target = `class:${cls}`;
                if (!allowed.has(target)) continue;
                try {
                    // Una clase repetida no identifica un elemento concreto. En componentes
                    // legacy solo la usamos como target cuando es única dentro de la sección.
                    if (stage.querySelectorAll(`.${CSS.escape(cls)}`).length !== 1) continue;
                } catch (_) { continue; }
                return { target, node: current, label: humanize(cls) };
            }
            current = current.parentElement;
        }
        return null;
    };

    const nodeForTarget = (target) => {
        try {
            if (target.startsWith('id:')) return stage.querySelector(`#${CSS.escape(target.slice(3))}`);
            if (target.startsWith('class:')) return stage.querySelector(`.${CSS.escape(target.slice(6))}`);
            return stage.querySelector(`[data-blumi-element="${CSS.escape(target)}"]`);
        } catch (_) { return null; }
    };

    const setLoader = (show, title = '', message = '') => {
        if (!loader) return;
        if (title && loaderTitle) loaderTitle.textContent = title;
        if (message && loaderMessage) loaderMessage.textContent = message;
        loader.hidden = !show;
        document.body.classList.toggle('blumi-personalize-busy', show);
    };

    const toast = (kind, title, message) => {
        const node = document.createElement('div');
        node.className = `toast ${kind === 'error' ? 'toast-error' : 'toast-success'}`;
        node.innerHTML = `<strong></strong><span></span>`;
        node.querySelector('strong').textContent = title;
        node.querySelector('span').textContent = message;
        document.body.appendChild(node);
    };

    const postJson = async (route, payload) => {
        const response = await fetch(`index.php?route=${encodeURIComponent(route)}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','Accept':'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({_csrf: csrf, instance_id: Number(instanceId), ...payload})
        });
        let data = null;
        try { data = await response.json(); } catch (_) {}
        if (!response.ok || !data?.ok) throw new Error(data?.error || 'No se pudo completar la operación.');
        return data;
    };

    const setPreview = (enabled) => {
        if (!pendingProposal || pendingProposal.requires_variant) return;
        if (!previewStyle) {
            previewStyle = document.createElement('style');
            previewStyle.id = `blumiPersonalizePreview-${instanceId}`;
            document.head.appendChild(previewStyle);
        }
        previewStyle.textContent = enabled ? String(pendingProposal.preview_css || '') : '';
        panel.querySelectorAll('[data-personalize-view]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.personalizeView === (enabled ? 'proposal' : 'original'));
        });
    };

    const clearProposal = () => {
        pendingProposal = null;
        if (previewStyle) previewStyle.textContent = '';
        if (proposalBox) proposalBox.hidden = true;
        if (variantBox) variantBox.hidden = true;
    };

    const renderList = (root, items) => {
        if (!root) return;
        root.innerHTML = '';
        (Array.isArray(items) ? items : []).slice(0, 6).forEach((item) => {
            const li = document.createElement('li');
            li.textContent = String(item);
            root.appendChild(li);
        });
    };

    const updateGenerateState = () => {
        generate.disabled = selected.size === 0 || draft.value.trim() === '';
    };

    const saveState = () => {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify([...selected.keys()]));
            sessionStorage.setItem(draftKey, draft.value || '');
        } catch (_) {}
    };

    const syncDraftSeed = () => {
        if (draftTouched) return;
        const labels = [...selected.values()].map((item) => item.label);
        draft.value = labels.length ? `Quiero ajustar ${labels.join(labels.length > 1 ? ', ' : '')}: ` : '';
    };

    const renderSelection = () => {
        selectionRoot.innerHTML = '';
        if (!selected.size) {
            const empty = document.createElement('span');
            empty.className = 'blumi-personalize-selection-empty';
            empty.textContent = 'Aún no has seleccionado elementos.';
            selectionRoot.appendChild(empty);
            if (hint) hint.textContent = active ? 'Haz clic sobre un elemento del componente.' : 'Primero activa la selección visual.';
        } else {
            selected.forEach((item, target) => {
                const chip = document.createElement('span');
                chip.className = 'blumi-personalize-chip';
                chip.textContent = item.label;
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.setAttribute('aria-label', `Quitar ${item.label}`);
                remove.textContent = '×';
                remove.addEventListener('click', () => {
                    item.node?.classList.remove('blumi-personalize-selected');
                    selected.delete(target);
                    clearProposal();
                    syncDraftSeed();
                    renderSelection();
                    saveState();
                });
                chip.appendChild(remove);
                selectionRoot.appendChild(chip);
            });
            if (hint) hint.textContent = `${selected.size} elemento${selected.size === 1 ? '' : 's'} seleccionado${selected.size === 1 ? '' : 's'}.`;
        }
        syncDraftSeed();
        updateGenerateState();
    };

    const setActive = (next) => {
        active = Boolean(next);
        document.body.classList.toggle('blumi-personalize-mode', active);
        section.classList.toggle('is-personalize-mode', active);
        panel.classList.toggle('is-selecting', active);
        toggle.textContent = active ? 'Terminar selección' : 'Seleccionar en Canvas';
        toggle.classList.toggle('button-primary', active);
        toggle.classList.toggle('button-secondary', !active);
        if (!active && hovered) {
            hovered.classList.remove('blumi-personalize-hover');
            hovered = null;
        }
        selected.forEach((item) => item.node?.classList.toggle('blumi-personalize-selected', active));
        if (hint && !selected.size) hint.textContent = active ? 'Haz clic sobre un elemento del componente.' : 'Primero activa la selección visual.';
    };

    toggle.addEventListener('click', () => {
        if (!active) {
            // Fase B selecciona sobre el Canvas DOM real. Volvemos a Desktop si estaba abierto el iframe responsive.
            document.querySelector('[data-viewport="desktop"]')?.click();
        }
        setActive(!active);
    });

    stage.addEventListener('pointerover', (event) => {
        if (!active) return;
        const candidate = targetForNode(event.target);
        const next = candidate?.node || null;
        if (next === hovered) return;
        hovered?.classList.remove('blumi-personalize-hover');
        hovered = next;
        if (hovered && !hovered.classList.contains('blumi-personalize-selected')) hovered.classList.add('blumi-personalize-hover');
    }, true);

    stage.addEventListener('pointerout', (event) => {
        if (!active || !hovered) return;
        const related = event.relatedTarget;
        if (related instanceof Node && hovered.contains(related)) return;
        hovered.classList.remove('blumi-personalize-hover');
        hovered = null;
    }, true);

    stage.addEventListener('click', (event) => {
        if (!active) return;
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        const candidate = targetForNode(event.target);
        if (!candidate) return;
        const existing = selected.get(candidate.target);
        if (existing) {
            existing.node?.classList.remove('blumi-personalize-selected');
            selected.delete(candidate.target);
        } else {
            candidate.node.classList.remove('blumi-personalize-hover');
            candidate.node.classList.add('blumi-personalize-selected');
            selected.set(candidate.target, candidate);
        }
        clearProposal();
        renderSelection();
        saveState();
    }, true);

    // Bloquea navegación/interacciones reales del componente mientras se seleccionan targets.
    ['submit','pointerdown'].forEach((name) => stage.addEventListener(name, (event) => {
        if (!active) return;
        event.preventDefault();
        event.stopPropagation();
    }, true));

    draft.addEventListener('input', () => {
        draftTouched = draft.value.trim() !== '';
        clearProposal();
        updateGenerateState();
        saveState();
    });

    generate.addEventListener('click', async () => {
        if (generate.disabled) return;
        setActive(false);
        clearProposal();
        setLoader(true, 'Blumi está preparando el cambio', 'Analizando tu instrucción y validando una propuesta segura…');
        generate.disabled = true;
        try {
            const data = await postJson('components.customization.propose', {
                targets: [...selected.keys()],
                instruction: draft.value.trim()
            });
            pendingProposal = data;
            const interpretation = data.interpretation || {};
            if (data.requires_variant) {
                if (variantReason) variantReason.textContent = interpretation.summary || 'La petición cambia la estructura o el concepto del componente.';
                if (variantBox) variantBox.hidden = false;
                return;
            }
            if (summary) summary.textContent = interpretation.summary || 'Ajuste visual sobre los elementos seleccionados.';
            renderList(willChange, interpretation.will_change || []);
            renderList(willKeep, interpretation.will_keep || ['Contenido', 'Paleta', 'Tipografía', 'Estructura']);
            if (proposalBox) proposalBox.hidden = false;
            setPreview(true);
        } catch (error) {
            toast('error', 'Revisa esto', error?.message || 'No se pudo generar la propuesta.');
        } finally {
            setLoader(false);
            updateGenerateState();
        }
    });

    panel.querySelectorAll('[data-personalize-view]').forEach((button) => button.addEventListener('click', () => {
        setPreview(button.dataset.personalizeView === 'proposal');
    }));

    discard?.addEventListener('click', () => {
        clearProposal();
        toast('success', 'Propuesta descartada', 'El componente vuelve a su estado actual.');
    });

    apply?.addEventListener('click', async () => {
        if (!pendingProposal?.patch) return;
        setLoader(true, 'Aplicando personalización', 'Guardando el cambio en esta página y preparando el Canvas…');
        apply.disabled = true;
        try {
            await postJson('components.customization.apply', {
                patch: pendingProposal.patch,
                interpretation: pendingProposal.interpretation || null,
                instruction: draft.value.trim()
            });
            try {
                sessionStorage.removeItem(storageKey);
                sessionStorage.removeItem(draftKey);
            } catch (_) {}
            window.location.reload();
        } catch (error) {
            apply.disabled = false;
            setLoader(false);
            toast('error', 'No se pudo aplicar', error?.message || 'Inténtalo nuevamente.');
        }
    });

    try {
        const restored = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
        if (Array.isArray(restored)) {
            restored.forEach((target) => {
                if (!allowed.has(target)) return;
                const node = nodeForTarget(target);
                if (node) selected.set(target, { target, node, label: humanize(target) });
            });
        }
        const savedDraft = sessionStorage.getItem(draftKey);
        if (savedDraft) {
            draft.value = savedDraft;
            draftTouched = true;
        }
    } catch (_) {}
    renderSelection();
})();
