import AuthLayoutTemplate from '@/layouts/auth/auth-split-layout';
import type { AuthLayoutProps } from '@/types';

export default function AuthLayout({
    title = '',
    description = '',
    showcase = true,
    children,
}: AuthLayoutProps) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            showcase={showcase}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
