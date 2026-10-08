import { Placeholder } from '@tiptap/extensions';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Code,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Quote,
    Strikethrough,
} from 'lucide-react';
import { useEffect, useImperativeHandle, useRef } from 'react';
import type { Ref } from 'react';
import { editorSuggestions } from '@/components/tickets/editor-suggestions';
import type { EditorSuggestionOptions } from '@/components/tickets/editor-suggestions';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * Imperative controls for parents, e.g. inserting a canned response at the cursor.
 */
export type RichTextEditorHandle = {
    insertContent: (html: string) => void;
    /** Replaces the whole draft, e.g. with an AI rewrite; onChange fires as for typing. */
    setContent: (html: string) => void;
};

type Props = {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    className?: string;
    /** Submit shortcut (⌘/Ctrl + Enter). */
    onSubmit?: () => void;
    tone?: 'default' | 'internal';
    autoFocus?: boolean;
    minHeight?: string;
    /** Enables the @mention, #ticket and /canned response popups (agent composer). */
    suggestions?: EditorSuggestionOptions;
    ref?: Ref<RichTextEditorHandle>;
};

export function RichTextEditor({
    value,
    onChange,
    placeholder,
    className,
    onSubmit,
    tone = 'default',
    autoFocus = false,
    minHeight = '8rem',
    suggestions,
    ref,
}: Props) {
    const { t } = useTranslation();
    // The extensions are created once; the triggers read the latest options from this ref.
    const suggestionOptions = useRef(suggestions);

    useEffect(() => {
        suggestionOptions.current = suggestions;
    });

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                link: { openOnClick: false, autolink: true },
            }),
            Placeholder.configure({
                placeholder: placeholder ?? t('Write a reply…'),
            }),
            ...(suggestions
                ? editorSuggestions(
                      () => suggestionOptions.current ?? { isInternal: false },
                  )
                : []),
        ],
        content: value,
        autofocus: autoFocus,
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class: 'prose-ticket focus:outline-none px-3 py-2.5',
                style: `min-height: ${minHeight}`,
            },
            handleKeyDown: (_view, event) => {
                if (
                    onSubmit &&
                    event.key === 'Enter' &&
                    (event.metaKey || event.ctrlKey)
                ) {
                    onSubmit();

                    return true;
                }

                return false;
            },
        },
        onUpdate: ({ editor: instance }) =>
            onChange(instance.isEmpty ? '' : instance.getHTML()),
    });

    useImperativeHandle(
        ref,
        () => ({
            insertContent: (html: string) =>
                editor?.chain().focus().insertContent(html).run(),
            setContent: (html: string) =>
                editor
                    ?.chain()
                    .setContent(html, { emitUpdate: true })
                    .focus('end')
                    .run(),
        }),
        [editor],
    );

    useEffect(() => {
        if (editor && value === '' && !editor.isEmpty) {
            editor.commands.clearContent();
        }
    }, [editor, value]);

    const state = useEditorState({
        editor,
        selector: ({ editor: instance }) => ({
            bold: instance?.isActive('bold') ?? false,
            italic: instance?.isActive('italic') ?? false,
            strike: instance?.isActive('strike') ?? false,
            code: instance?.isActive('code') ?? false,
            bulletList: instance?.isActive('bulletList') ?? false,
            orderedList: instance?.isActive('orderedList') ?? false,
            blockquote: instance?.isActive('blockquote') ?? false,
            link: instance?.isActive('link') ?? false,
        }),
    });

    const setLink = () => {
        if (!editor) {
            return;
        }

        const previous = editor.getAttributes('link').href as
            | string
            | undefined;
        const url = window.prompt(t('Link URL'), previous ?? 'https://');

        if (url === null) {
            return;
        }

        if (url === '') {
            editor.chain().focus().unsetLink().run();

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url })
            .run();
    };

    const buttons = [
        {
            key: 'bold',
            icon: Bold,
            label: t('Bold'),
            run: () => editor?.chain().focus().toggleBold().run(),
        },
        {
            key: 'italic',
            icon: Italic,
            label: t('Italic'),
            run: () => editor?.chain().focus().toggleItalic().run(),
        },
        {
            key: 'strike',
            icon: Strikethrough,
            label: t('Strikethrough'),
            run: () => editor?.chain().focus().toggleStrike().run(),
        },
        {
            key: 'code',
            icon: Code,
            label: t('Code'),
            run: () => editor?.chain().focus().toggleCode().run(),
        },
        {
            key: 'bulletList',
            icon: List,
            label: t('Bulleted list'),
            run: () => editor?.chain().focus().toggleBulletList().run(),
        },
        {
            key: 'orderedList',
            icon: ListOrdered,
            label: t('Numbered list'),
            run: () => editor?.chain().focus().toggleOrderedList().run(),
        },
        {
            key: 'blockquote',
            icon: Quote,
            label: t('Quote'),
            run: () => editor?.chain().focus().toggleBlockquote().run(),
        },
        { key: 'link', icon: LinkIcon, label: t('Link'), run: setLink },
    ] as const;

    return (
        <div
            data-slot="rich-text-editor"
            className={cn(
                'overflow-hidden rounded-lg border bg-background shadow-xs transition-colors focus-within:ring-2 focus-within:ring-ring/40',
                tone === 'internal' &&
                    'border-amber-300 bg-amber-50/60 dark:border-amber-500/40 dark:bg-amber-500/5',
                className,
            )}
        >
            <div className="flex flex-wrap items-center gap-0.5 border-b border-inherit px-1.5 py-1">
                {buttons.map(({ key, icon: Icon, label, run }) => (
                    <button
                        key={key}
                        type="button"
                        title={label}
                        aria-label={label}
                        aria-pressed={state?.[key] ?? false}
                        onClick={run}
                        className={cn(
                            'rounded p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                            state?.[key] && 'bg-accent text-foreground',
                        )}
                    >
                        <Icon className="size-3.5" />
                    </button>
                ))}
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}
