<?php

use Pt_Commons_TranslateUtility as T;

/**
 * Builds and shows the items that evaluation stores in
 * shipment_participant_map.failure_reason.
 *
 * An item built here keeps a reason code and its values, so warningHtml() writes the
 * message in the reader's language and escapes every value. The item also keeps the
 * English 'warning' and 'correctiveAction' text, so code that reads those keys
 * directly still works. An item without a code is shown as stored.
 */
final class Pt_Evaluation_FailureReason
{
    /**
     * @param array<string, string|list<string>> $params English values, keyed by placeholder name
     * @return array<string, mixed>
     */
    public static function dts(
        Pt_Evaluation_Dts_Reason $reason,
        array $params,
        Pt_Evaluation_Dts_CorrectiveAction $action
    ): array {
        $item = [
            'code' => $reason->value,
            'params' => $params,
            'correctiveActionId' => $action->value,
        ];
        $item['warning'] = self::warningHtml($item, false);
        $item['correctiveAction'] = $action->action();
        return $item;
    }

    /**
     * The warning as HTML. With $translate, the message and its translatable values
     * are in the reader's language.
     *
     * @param array<string, mixed>|string $item a stored item, or a legacy plain string
     */
    public static function warningHtml(array|string $item, bool $translate = true): string
    {
        if (is_string($item)) {
            return $item;
        }
        $reason = self::reason($item);
        if ($reason === null) {
            return (string) ($item['warning'] ?? '');
        }
        $translatable = $reason->translatedParams();
        $replace = [];
        foreach ((array) ($item['params'] ?? []) as $name => $value) {
            // A list value, such as several test names, is shown comma-separated.
            $values = array_map('strval', (array) $value);
            if ($translate && in_array($name, $translatable, true)) {
                $values = array_map(T::safeTranslate(...), $values);
            }
            $replace['%' . $name . '%'] = '<strong>' . Pt_Commons_MiscUtility::escapeHtml(implode(', ', $values)) . '</strong>';
        }
        $template = $translate ? T::safeTranslate($reason->template()) : $reason->template();
        return strtr($template, $replace);
    }

    /**
     * The corrective action as HTML, in the reader's language. Stored text from an
     * item without an action ID is shown as stored, since some schemes keep markup
     * in it.
     *
     * @param array<string, mixed>|string $item a stored item, or a legacy plain string
     */
    public static function correctiveActionHtml(array|string $item): string
    {
        if (is_string($item)) {
            return '';
        }
        $action = Pt_Evaluation_Dts_CorrectiveAction::tryFrom((int) ($item['correctiveActionId'] ?? 0));
        if ($action !== null) {
            return Pt_Commons_MiscUtility::escapeHtml($action->actionText());
        }
        return T::safeTranslate((string) ($item['correctiveAction'] ?? ''));
    }

    /** @param array<string, mixed> $item */
    private static function reason(array $item): ?Pt_Evaluation_Dts_Reason
    {
        $code = $item['code'] ?? null;
        return is_string($code) ? Pt_Evaluation_Dts_Reason::tryFrom($code) : null;
    }
}
