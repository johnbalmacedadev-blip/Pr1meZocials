import { Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import LegalLayout from '@/Layouts/LegalLayout';
import { withBase } from '@/lib/utils';
import type { SharedProps } from '@/types';

export default function Privacy({
    appName,
    appUrl,
    updatedAt,
}: {
    appName: string;
    appUrl: string;
    updatedAt: string;
}) {
    const basePath = usePage<SharedProps>().props.basePath ?? '';

    return (
        <LegalLayout title="Privacy Policy">
            <article className="space-y-8 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
                <header className="space-y-2 border-b border-zinc-100 pb-6">
                    <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">Legal</p>
                    <h1 className="text-3xl font-semibold tracking-tight">{appName} Privacy Policy</h1>
                    <p className="text-sm text-zinc-600">Last updated: {updatedAt}</p>
                    <p className="text-sm text-zinc-600">
                        This Privacy Policy explains how {appName} (“we”, “us”, or “our”) collects, uses, and protects information when you use our creative operations and social posting platform at{' '}
                        <a className="font-medium text-brand-700" href={appUrl}>{appUrl}</a>.
                    </p>
                </header>

                <Section title="1. Who this applies to">
                    <p>This policy applies to team members invited to {appName}, administrators who connect Meta/Facebook accounts, and visitors who open this page.</p>
                </Section>

                <Section title="2. Information we collect">
                    <ul className="list-disc space-y-2 pl-5">
                        <li><strong>Account data:</strong> name, email or username, role, brand access, and authentication credentials (passwords are hashed).</li>
                        <li><strong>Workspace content:</strong> content ideas, captions, schedules, creative assets, notes, campaign data, and assignment records you create in the app.</li>
                        <li><strong>Meta / Facebook connection data:</strong> when you Connect with Meta, we receive authorization tokens, Facebook Page IDs, Page names/handles, and Page access tokens needed to publish and read basic post metrics on your behalf.</li>
                        <li><strong>Published post data:</strong> captions, media you upload for posting, publish times, Facebook post IDs, and engagement metrics we sync from Meta (such as likes, comments, shares, views, or reach when available).</li>
                        <li><strong>Technical data:</strong> session cookies, IP address, browser type, and basic server logs used for security and troubleshooting.</li>
                    </ul>
                </Section>

                <Section title="3. How we use information">
                    <ul className="list-disc space-y-2 pl-5">
                        <li>Operate the {appName} workspace (content planning, production, calendars, and team collaboration).</li>
                        <li>Authenticate users and enforce permissions.</li>
                        <li>Connect your Facebook Pages, publish or schedule posts you approve, and refresh Page lists/tokens.</li>
                        <li>Show posting history and analytics pulled from Meta for Pages you connected.</li>
                        <li>Maintain security, prevent abuse, and improve reliability.</li>
                    </ul>
                    <p className="mt-3">We do not sell your personal information. We do not use Meta data to build advertising audiences unrelated to operating your connected Pages.</p>
                </Section>

                <Section title="4. Meta Platform data">
                    <p>
                        Facebook/Meta login and Page posting are optional features. When enabled, we only request permissions needed to list Pages you manage, create posts (including scheduled posts), and read engagement insights for posts created through {appName}.
                    </p>
                    <p className="mt-3">
                        Access tokens are stored securely (encrypted at rest when possible) and used only to perform actions you initiate or schedule in the app. You can disconnect Meta from Page settings; you may also remove the app from your Facebook settings.
                    </p>
                </Section>

                <Section title="5. Sharing">
                    <ul className="list-disc space-y-2 pl-5">
                        <li><strong>Meta Platforms:</strong> content and tokens are sent to Facebook Graph API to publish posts and retrieve metrics you request.</li>
                        <li><strong>Hosting providers:</strong> infrastructure that hosts {appName} (for example web hosting and databases) may process data to run the service.</li>
                        <li><strong>Team members:</strong> people in your organization with permission can see workspace content and connected Page names according to your role settings.</li>
                        <li>We may disclose information if required by law or to protect the security of the service.</li>
                    </ul>
                </Section>

                <Section title="6. Retention">
                    <p>
                        We keep account and workspace data while your organization uses {appName}. Meta tokens are retained until you disconnect Meta or replace them by reconnecting. You (or an admin) may delete drafts, posts records, or request removal of account data by contacting the administrator of your {appName} workspace.
                    </p>
                </Section>

                <Section title="7. Security">
                    <p>
                        We use industry-standard measures such as encrypted connections (HTTPS), hashed passwords, session controls, and encrypted storage for sensitive tokens where supported. No method of transmission or storage is 100% secure.
                    </p>
                </Section>

                <Section title="8. Your choices">
                    <ul className="list-disc space-y-2 pl-5">
                        <li>Update profile details in the app.</li>
                        <li>Disconnect Meta from Page settings.</li>
                        <li>Ask an administrator to deactivate your user account.</li>
                        <li>Remove {appName}’s access from your Facebook account settings at any time.</li>
                    </ul>
                </Section>

                <Section title="9. Children">
                    <p>{appName} is intended for business use by adults. We do not knowingly collect personal information from children under 13 (or the minimum age required in your jurisdiction).</p>
                </Section>

                <Section title="10. Changes">
                    <p>We may update this Privacy Policy from time to time. The “Last updated” date at the top will change when we do. Continued use of the service after updates means you accept the revised policy.</p>
                </Section>

                <Section title="11. Contact">
                    <p>
                        Questions about privacy or Meta data use: contact your {appName} workspace administrator, or reach the operators of this installation via the site at{' '}
                        <a className="font-medium text-brand-700" href={appUrl}>{appUrl}</a>.
                    </p>
                    <p className="mt-3 text-sm text-zinc-600">
                        See also our <Link href={withBase('/terms', basePath)} className="font-medium text-brand-700">Terms of Service</Link>.
                    </p>
                </Section>
            </article>
        </LegalLayout>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-3">
            <h2 className="text-lg font-semibold text-zinc-900">{title}</h2>
            <div className="space-y-2 text-sm leading-relaxed text-zinc-700">{children}</div>
        </section>
    );
}
