<?php

/**
 * Shared look for the emails ePT writes itself: password resets, login email
 * changes and admin notices. Emails that admins write from mail templates do
 * not go through this class.
 *
 * Mail clients drop <style> blocks and ignore most layout CSS, so every helper
 * uses inline styles and tables. Each block ends with a newline because the
 * send job builds the plain-text part with strip_tags().
 */
final class Application_Service_EmailLayout
{
    public const BRAND = '#1a56db';
    private const TEXT = '#222222';
    private const MUTED = '#5f6b7a';
    private const FONT = 'Arial,Helvetica,sans-serif';

    /**
     * Wraps the body in a white card on a grey page, with an ePT header and a
     * footer that names the site the email came from. $preheader is the
     * preview line that inboxes show after the subject.
     */
    public static function page(string $bodyHtml, string $preheader = ''): string
    {
        $host = (string) (parse_url(self::siteUrl(), PHP_URL_HOST) ?: '');
        $footer = $host !== ''
            ? sprintf(Pt_Commons_TranslateUtility::safeTranslate('This is an automated email from ePT at %s.'), self::e($host))
            : Pt_Commons_TranslateUtility::safeTranslate('This is an automated email from ePT.');

        return '<div style="margin:0;padding:24px 12px;background:#f2f4f7;">' . "\n"
            . ($preheader !== ''
                ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . self::e($preheader) . '</div>' . "\n"
                : '')
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;max-width:560px;margin:0 auto;">' . "\n"
            . '<tr><td style="padding:0 4px 12px;font-family:' . self::FONT . ';font-size:18px;font-weight:bold;color:' . self::BRAND . ';">ePT</td></tr>' . "\n"
            . '<tr><td style="background:#ffffff;border:1px solid #dde3ea;border-radius:8px;padding:28px 28px 24px;font-family:' . self::FONT . ';font-size:15px;line-height:1.55;color:' . self::TEXT . ';">' . "\n"
            . $bodyHtml
            . '</td></tr>' . "\n"
            . '<tr><td style="padding:14px 4px 0;font-family:' . self::FONT . ';font-size:12px;line-height:1.5;color:#7a8391;">' . $footer . '</td></tr>' . "\n"
            . '</table>' . "\n"
            . '</div>';
    }

    /** A paragraph. $html is already escaped. */
    public static function p(string $html, bool $last = false): string
    {
        return '<p style="margin:0 0 ' . ($last ? '0' : '16px') . ';">' . $html . '</p>' . "\n";
    }

    /** "Dear Ana Silva," or "Dear Participant," when there is no name. */
    public static function greeting(?string $name): string
    {
        $name = trim((string) $name);
        $text = $name !== ''
            ? Pt_Commons_TranslateUtility::safeTranslate('Dear') . ' ' . self::e($name)
            : Pt_Commons_TranslateUtility::safeTranslate('Dear Participant');
        return self::p($text . ',');
    }

    /**
     * A table-cell button, the form that still renders as a button in Outlook.
     * $label is plain text.
     */
    public static function button(string $url, string $label): string
    {
        return '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:4px 0 20px;"><tr>'
            . '<td style="background:' . self::BRAND . ';border-radius:5px;">'
            . '<a href="' . self::e($url) . '" style="display:inline-block;padding:11px 24px;font-family:' . self::FONT . ';font-size:15px;color:#ffffff;font-weight:bold;text-decoration:none;">' . self::e($label) . '</a>'
            . '</td></tr></table>' . "\n";
    }

    /** The full link under a button, for mail clients that block buttons. */
    public static function fallbackLink(string $url): string
    {
        return '<p style="margin:0 0 20px;font-size:13px;color:' . self::MUTED . ';">'
            . Pt_Commons_TranslateUtility::safeTranslate('If the button does not work, copy this link into your browser:') . '<br/>' . "\n"
            . '<a href="' . self::e($url) . '" style="color:' . self::BRAND . ';word-break:break-all;">' . self::e($url) . '</a></p>' . "\n";
    }

    /** A grey note set apart by a rule, for "did not ask for this" text. $html is already escaped. */
    public static function note(string $html): string
    {
        return '<p style="margin:0 0 16px;padding-top:14px;border-top:1px solid #eceff3;font-size:13px;color:' . self::MUTED . ';">' . $html . '</p>' . "\n";
    }

    public static function signoff(): string
    {
        return self::p(Pt_Commons_TranslateUtility::safeTranslate('Thank you') . ',<br/>' . "\n" . Pt_Commons_TranslateUtility::safeTranslate('ePT Support'), true);
    }

    /** The configured site address without a trailing slash. */
    public static function siteUrl(): string
    {
        try {
            $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            return rtrim((string) $conf->get('domain'), '/');
        } catch (Throwable) {
            return '';
        }
    }

    public static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
