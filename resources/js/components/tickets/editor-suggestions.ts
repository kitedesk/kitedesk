import Mention from '@tiptap/extension-mention';
import { mergeAttributes, Node } from '@tiptap/react';
import type { Editor, Range } from '@tiptap/react';
import { AtSign, Hash, MessageSquareText } from 'lucide-react';
import { fillPlaceholders } from '@/components/tickets/canned-response-picker';
import type {
    CannedResponseOption,
    PlaceholderValues,
} from '@/components/tickets/canned-response-picker';
import { suggestionPopup } from '@/components/tickets/suggestion-popup';
import type { SuggestionEntry } from '@/components/tickets/suggestion-popup';
import { t } from '@/lib/i18n';
import { search } from '@/routes/agent';
import { show } from '@/routes/agent/tickets';

/**
 * What the reply composer's @, # and / triggers need. Read on every keystroke,
 * so the editor can be created once while the composer mode and data change.
 */
export type EditorSuggestionOptions = {
    /** @mentions only work in internal notes; # inserts a link there and plain text in public replies. */
    isInternal: boolean;
    currentTicketId?: number;
    /** Undefined while the deferred list is loading. */
    cannedResponses?: CannedResponseOption[];
    placeholderValues?: PlaceholderValues;
};

type UserMatch = { id: number; name: string; email: string };
type TicketMatch = { id: number; number: string; subject: string };

async function searchAgent(
    query: Record<string, string | number>,
    signal: AbortSignal,
): Promise<{ users: UserMatch[]; tickets: TicketMatch[] }> {
    const response = await fetch(search.url({ query }), {
        headers: { Accept: 'application/json' },
        signal,
    });

    return response.ok ? response.json() : { users: [], tickets: [] };
}

/**
 * The value behind the chosen popup row. Mention types `props` as mention attributes,
 * but our `items` return SuggestionEntry rows and every trigger has its own command.
 */
function picked<T>(props: unknown): T {
    return (props as SuggestionEntry<T>).value;
}

/**
 * Insert an inline node where the trigger text was, followed by a space.
 */
function insertInline(
    editor: Editor,
    range: Range,
    content: Record<string, unknown> | string,
): void {
    editor
        .chain()
        .focus()
        .insertContentAt(range, [
            typeof content === 'string'
                ? { type: 'text', text: content }
                : content,
            { type: 'text', text: ' ' },
        ])
        .run();
}

/**
 * A ticket reference in an internal note, stored as `<a data-type="ticket" data-id="42">`.
 */
export const TicketReference = Node.create({
    name: 'ticketReference',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: false,

    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-id'),
                renderHTML: (attributes) => ({ 'data-id': attributes.id }),
            },
            label: {
                default: null,
                parseHTML: (element) => element.textContent,
                rendered: false,
            },
        };
    },

    parseHTML() {
        // Ahead of the link mark, which would otherwise take the <a>.
        return [{ tag: 'a[data-type="ticket"]', priority: 100 }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return [
            'a',
            mergeAttributes(
                {
                    'data-type': 'ticket',
                    href: show.url(Number(node.attrs.id)),
                },
                HTMLAttributes,
            ),
            node.attrs.label,
        ];
    },

    renderText({ node }) {
        return node.attrs.label;
    },
});

/**
 * @mention agents, #reference tickets and /insert canned responses.
 */
export function editorSuggestions(
    options: () => EditorSuggestionOptions,
): [typeof Mention, typeof TicketReference] {
    const mentions = Mention.configure({
        suggestions: [
            {
                char: '@',
                debounce: 150,
                allow: () => options().isInternal,
                items: async ({ query, signal }) =>
                    (
                        await searchAgent({ q: query, staff_only: 1 }, signal)
                    ).users.map((user): SuggestionEntry<UserMatch> => ({
                        key: `user-${user.id}`,
                        title: user.name,
                        hint: user.email,
                        icon: AtSign,
                        value: user,
                    })),
                command: ({ editor, range, props }) => {
                    const user = picked<UserMatch>(props);

                    insertInline(editor, range, {
                        type: 'mention',
                        attrs: {
                            id: String(user.id),
                            label: user.name,
                            mentionSuggestionChar: '@',
                        },
                    });
                },
                render: suggestionPopup(() => t('No agents found')),
            },
            {
                char: '#',
                debounce: 200,
                items: async ({ query, signal }) => {
                    if (query === '') {
                        return [];
                    }

                    const { tickets } = await searchAgent({ q: query }, signal);

                    return tickets
                        .filter(
                            (ticket) => ticket.id !== options().currentTicketId,
                        )
                        .map((ticket): SuggestionEntry<TicketMatch> => ({
                            key: `ticket-${ticket.id}`,
                            title: ticket.subject,
                            hint: ticket.number,
                            icon: Hash,
                            value: ticket,
                        }));
                },
                command: ({ editor, range, props }) => {
                    const ticket = picked<TicketMatch>(props);

                    insertInline(
                        editor,
                        range,
                        options().isInternal
                            ? {
                                  type: TicketReference.name,
                                  attrs: {
                                      id: String(ticket.id),
                                      label: ticket.number,
                                  },
                              }
                            : ticket.number,
                    );
                },
                render: suggestionPopup(() =>
                    t('Type a ticket number or subject'),
                ),
            },
            {
                char: '/',
                items: ({ query }) => {
                    const term = query.toLowerCase();

                    return (options().cannedResponses ?? [])
                        .filter((response) =>
                            response.title.toLowerCase().includes(term),
                        )
                        .slice(0, 8)
                        .map(
                            (
                                response,
                            ): SuggestionEntry<CannedResponseOption> => ({
                                key: `canned-${response.id}`,
                                title: response.title,
                                icon: MessageSquareText,
                                value: response,
                            }),
                        );
                },
                command: ({ editor, range, props }) => {
                    const response = picked<CannedResponseOption>(props);

                    editor
                        .chain()
                        .focus()
                        .deleteRange(range)
                        .insertContent(
                            fillPlaceholders(
                                response.body,
                                options().placeholderValues ?? {},
                            ),
                        )
                        .run();
                },
                render: suggestionPopup(() =>
                    options().cannedResponses === undefined
                        ? t('Loading…')
                        : t('No canned responses'),
                ),
            },
        ],
    });

    return [mentions, TicketReference];
}
