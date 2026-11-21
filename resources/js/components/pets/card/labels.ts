import type { useTranslations } from '@/composables/useTranslations';

type Translate = ReturnType<typeof useTranslations>['t'];

/**
 * "7 years" / "1 year" / "3 months", from the derived `age` in years.
 *
 * Under a year it is shown in months, since "0.25 years" is not how anybody
 * describes a puppy; a whole number of years is shown as such and anything
 * in between is rounded to one decimal. Shared by the card's meta line, its
 * hover bar and the detail header so the three cannot disagree about the
 * plural. Takes the caller's `t` rather than calling `useTranslations()`
 * itself: this file is a plain module, not a component, and the composable
 * reads `usePage()`. All four keys exist in both catalogues.
 */
export function ageLabel(t: Translate, age: number): string {
    if (age < 1) {
        const months = Math.max(1, Math.round(age * 12));

        return t(months === 1 ? 'pets.age_month' : 'pets.age_months', {
            count: String(months),
        });
    }

    const years = Math.round(age * 10) / 10;

    return t(years === 1 ? 'pets.age_year' : 'pets.age_years', {
        count: String(years),
    });
}

/**
 * "2 males, 1 female", for a litter. Null for a single animal, whose sex is
 * carried by `gender` and rendered by the caller.
 */
export function litterLabel(
    t: Translate,
    quantity: number,
    maleCount: number | null,
    femaleCount: number | null,
): string | null {
    if (quantity <= 1) {
        return null;
    }

    return t('pets.litter_sexes', {
        male: String(maleCount ?? 0),
        female: String(femaleCount ?? 0),
    });
}
