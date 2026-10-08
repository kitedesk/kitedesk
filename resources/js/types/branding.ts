export type HeaderLink = { label: string; url: string };

/**
 * The installation's look, shared with every page (see Branding::sharedProps()).
 */
export type Branding = {
    logo: string | null;
    logoDark: string | null;
    /** Brand color overrides for the page head; empty for the stock colors. */
    stylesheet: string;
    helpTitle: string | null;
    helpSubtitle: string | null;
    headerLinks: HeaderLink[];
    portalFooter: string | null;
    showPoweredBy: boolean;
    customCss: string | null;
};
