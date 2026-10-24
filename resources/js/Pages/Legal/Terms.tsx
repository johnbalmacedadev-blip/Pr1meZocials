import { Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import LegalLayout from '@/Layouts/LegalLayout';
import { withBase } from '@/lib/utils';
import type { SharedProps } from '@/types';

export default function Terms({
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
        <LegalLayout title="Terms of Service">
            <article className="space-y-8 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
                <header className="space-y-2 border-b border-zinc-100 pb-6">
                    <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">Legal</p>
                    <h1 className="text-3xl font-semibold tracking-tight">{appName} Terms of Service</h1>
                    <p className="text-sm text-zinc-600">Last updated: {updatedAt}</p>
                    <p className="text-sm text-zinc-600">
                        These Terms of Service (“Terms”) govern access to and use of {appName} at{' '}
                        <a className="font-medium text-brand-700" href={appUrl}>{appUrl}</a>. By signing in or using the service, you agree to these Terms.
                    </p>
                </header>

                <Section title="1. The service">
                    <p>
                        {appName} is a creative operations and social media posting workspace. Features may include content planning, asset management, calendars, team assignments, and publishing or scheduling posts to Facebook Pages you connect through Meta.
                    </p>
                </Section>

                <Section title="2. Accounts and access">
                    <ul className="list-disc space-y-2 pl-5">
                        <li>Access is by invitation or account created by an administrator.</li>
                        <li>You are responsible for keeping your login credentials confidential and for activity under your account.</li>
                        <li>Administrators may grant, change, or revoke roles and brand access at any time.</li>
                    </ul>
                </Section>

                <Section title="3. Acceptable use">
                    <p>You agree not to:</p>
                    <ul className="mt-2 list-disc space-y-2 pl-5">
                        <li>Use the service for unlawful, harmful, deceptive, or infringing content.</li>
                        <li>Attempt to access other organizations’ data or bypass security controls.</li>
                        <li>Abuse Meta/Facebook APIs, scrape data beyond what the product provides, or violate Meta Platform Terms.</li>
                        <li>Upload malware or interfere with the operation of the service.</li>
                    </ul>
                </Section>

                <Section title="4. Content you submit">
                    <p>
                        You retain ownership of content you upload or create (ideas, captions, media, notes). You grant us a limited license to host, process, display, and transmit that content solely to operate {appName} for your organization—including sending posts and media to Meta when you choose to publish or schedule.
                    </p>
                    <p className="mt-3">
                        You represent that you have the rights and permissions needed for any content you publish, including rights to images, video, music, trademarks, and personal likenesses.
                    </p>
                </Section>

                <Section title="5. Meta / Facebook features">
                    <ul className="list-disc space-y-2 pl-5">
                        <li>Connecting Meta is optional. You authorize {appName} to act on Facebook Pages you select using tokens Meta issues to you.</li>
                        <li>You must comply with Meta’s terms, community standards, advertising policies, and any Page roles/permissions Facebook requires.</li>
                        <li>Scheduling uses Facebook’s scheduled publish capabilities where available; Facebook—not {appName}—ultimately delivers the post at the scheduled time.</li>
                        <li>Meta may change APIs, revoke tokens, or limit features; we are not responsible for Meta outages or policy changes.</li>
                        <li>You can disconnect Meta at any time in Page settings or by removing the app from Facebook.</li>
                    </ul>
                </Section>

                <Section title="6. Third-party services">
                    <p>
                        The service may depend on third parties (hosting, Meta Platforms, email, etc.). Their terms and privacy practices apply to their services. {appName} is not responsible for third-party failures outside our reasonable control.
                    </p>
                </Section>

                <Section title="7. Availability and changes">
                    <p>
                        We aim for reliable uptime but do not guarantee uninterrupted availability. We may modify, suspend, or discontinue features, including Meta integrations, with or without notice when needed for security, legal, or operational reasons.
                    </p>
                </Section>

                <Section title="8. Disclaimer of warranties">
                    <p>
                        THE SERVICE IS PROVIDED “AS IS” AND “AS AVAILABLE” WITHOUT WARRANTIES OF ANY KIND, WHETHER EXPRESS OR IMPLIED, INCLUDING MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, AND NON-INFRINGEMENT, TO THE MAXIMUM EXTENT PERMITTED BY LAW.
                    </p>
                </Section>

                <Section title="9. Limitation of liability">
                    <p>
                        TO THE MAXIMUM EXTENT PERMITTED BY LAW, {appName.toUpperCase()} AND ITS OPERATORS ARE NOT LIABLE FOR INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, OR FOR LOST PROFITS, DATA, OR GOODWILL, ARISING FROM YOUR USE OF THE SERVICE OR FROM META/FACEBOOK ACTIONS, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.
                    </p>
                </Section>

                <Section title="10. Termination">
                    <p>
                        We or your administrator may suspend or terminate access if you violate these Terms or for operational reasons. Upon termination, your right to use the service stops. Provisions that should survive (ownership, disclaimers, limitations) will survive.
                    </p>
                </Section>

                <Section title="11. Changes to these Terms">
                    <p>
                        We may update these Terms. The “Last updated” date will change when we do. Continued use after changes constitutes acceptance of the updated Terms.
                    </p>
                </Section>

                <Section title="12. Contact">
                    <p>
                        Questions about these Terms: contact your {appName} workspace administrator, or the operators of this installation via{' '}
                        <a className="font-medium text-brand-700" href={appUrl}>{appUrl}</a>.
                    </p>
                    <p className="mt-3 text-sm text-zinc-600">
                        See also our <Link href={withBase('/privacy', basePath)} className="font-medium text-brand-700">Privacy Policy</Link>.
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
