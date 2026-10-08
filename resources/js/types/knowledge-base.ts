export type ArticleStatus = 'draft' | 'published';

export type KbArticleSummary = {
    id: number;
    title: string;
    slug: string;
    status: ArticleStatus;
    is_published: boolean;
    view_count: number;
    helpful_count: number;
    not_helpful_count: number;
    updated_at: string | null;
};

export type KbSection = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    articles: KbArticleSummary[];
};

export type KbCategory = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sections: KbSection[];
};

export type KbArticle = {
    id: number;
    section_id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body: string;
    status: ArticleStatus;
    is_published: boolean;
    published_at: string | null;
    updated_at: string | null;
};

export type KbSectionOption = {
    id: number;
    name: string;
    category: string;
};
