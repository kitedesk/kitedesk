/**
 * The support widget's embed script, served at /widget.js. It draws a button in the
 * corner of the host page that opens the widget's frame (/widget/frame) in an iframe.
 *
 * It has no imports: the server sends it as a classic script, preceded by
 * `window.KiteDeskWidgetConfig`. Host pages can control it with `window.KiteDesk`.
 * Messages between this script and the frame are prefixed with `kitedesk:`.
 */

type WidgetConfig = {
    frameUrl: string;
    position: 'right' | 'left';
    label: string;
    color: string;
    textColor: string;
};

type Visitor = { name?: string; email?: string };

type WidgetApi = {
    open: () => void;
    close: () => void;
    toggle: () => void;
    /** Fill in the visitor's name and email, e.g. for someone signed in to the host site. */
    identify: (visitor: Visitor) => void;
};

type WidgetWindow = Window & {
    KiteDeskWidgetConfig?: WidgetConfig;
    KiteDesk?: WidgetApi;
};

// Everything stays inside this function: the script runs in the host page's global scope.
(function () {
    const CHAT_ICON =
        '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>';
    const CLOSE_ICON =
        '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

    function styles(config: WidgetConfig): string {
        const side = config.position === 'left' ? 'left' : 'right';

        return `
            :host { all: initial; }
            .launcher {
                position: fixed; bottom: 20px; ${side}: 20px; z-index: 2147483000;
                display: inline-flex; align-items: center; gap: 8px;
                height: 52px; padding: 0 18px; border: 0; border-radius: 26px;
                background: ${config.color}; color: ${config.textColor};
                font: 600 15px/1 system-ui, -apple-system, 'Segoe UI', sans-serif;
                box-shadow: 0 6px 24px rgb(0 0 0 / 0.18); cursor: pointer;
                transition: transform 0.2s cubic-bezier(0.32, 0.72, 0, 1);
            }
            .launcher:hover { transform: translateY(-2px); }
            .launcher:active { transform: scale(0.98); }
            .launcher:focus-visible { outline: 3px solid ${config.color}; outline-offset: 3px; }
            .launcher.open { width: 52px; padding: 0; justify-content: center; }
            .launcher.open .label { display: none; }
            .panel {
                position: fixed; bottom: 84px; ${side}: 20px; z-index: 2147483000;
                width: 380px; height: min(640px, calc(100vh - 108px));
                border-radius: 16px; overflow: hidden; background: #fff;
                box-shadow: 0 12px 48px rgb(0 0 0 / 0.22);
                opacity: 0; transform: translateY(12px); pointer-events: none;
                transition: opacity 0.25s cubic-bezier(0.32, 0.72, 0, 1), transform 0.25s cubic-bezier(0.32, 0.72, 0, 1);
            }
            .panel.open { opacity: 1; transform: none; pointer-events: auto; }
            .panel iframe { width: 100%; height: 100%; border: 0; display: block; }
            @media (max-width: 480px) {
                .panel { inset: 0; width: 100%; height: 100%; border-radius: 0; }
                .launcher.open { display: none; }
            }
        `;
    }

    const page = window as WidgetWindow;
    const config = page.KiteDeskWidgetConfig;

    if (!config || page.KiteDesk) {
        return;
    }

    const origin = new URL(config.frameUrl).origin;
    const host = document.createElement('div');
    host.setAttribute('data-kitedesk-widget', '');
    const root = host.attachShadow({ mode: 'open' });

    const style = document.createElement('style');
    style.textContent = styles(config);

    const launcher = document.createElement('button');
    launcher.type = 'button';
    launcher.className = 'launcher';
    launcher.setAttribute('aria-expanded', 'false');

    const panel = document.createElement('div');
    panel.className = 'panel';

    root.append(style, panel, launcher);

    let frame: HTMLIFrameElement | null = null;
    let ready = false;
    let isOpen = false;
    let visitor: Visitor = {};

    const render = () => {
        launcher.classList.toggle('open', isOpen);
        launcher.setAttribute('aria-expanded', String(isOpen));
        launcher.setAttribute('aria-label', isOpen ? '×' : config.label);
        launcher.innerHTML = isOpen
            ? CLOSE_ICON
            : `${CHAT_ICON}<span class="label"></span>`;
        launcher.querySelector('.label')?.append(config.label);
        panel.classList.toggle('open', isOpen);
    };

    const post = (message: Record<string, unknown>) => {
        if (frame && ready) {
            frame.contentWindow?.postMessage(message, origin);
        }
    };

    const open = () => {
        // The frame loads on first use, so pages that never open the widget don't pay for it.
        if (!frame) {
            frame = document.createElement('iframe');
            frame.src = config.frameUrl;
            frame.title = config.label;
            frame.allow = 'clipboard-write';
            panel.append(frame);
        }

        isOpen = true;
        render();
        post({ type: 'kitedesk:opened' });
    };

    const close = () => {
        isOpen = false;
        render();
        launcher.focus();
    };

    window.addEventListener('message', (event) => {
        if (event.origin !== origin || event.source !== frame?.contentWindow) {
            return;
        }

        const data = event.data as { type?: string } | null;

        if (data?.type === 'kitedesk:ready') {
            ready = true;
            post({ type: 'kitedesk:identify', ...visitor });
        } else if (data?.type === 'kitedesk:close') {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen) {
            close();
        }
    });

    launcher.addEventListener('click', () => (isOpen ? close() : open()));

    page.KiteDesk = {
        open,
        close,
        toggle: () => (isOpen ? close() : open()),
        identify: (details) => {
            visitor = { name: details.name, email: details.email };
            post({ type: 'kitedesk:identify', ...visitor });
        },
    };

    render();

    const mount = () => document.body.append(host);

    if (document.body) {
        mount();
    } else {
        document.addEventListener('DOMContentLoaded', mount);
    }
})();
