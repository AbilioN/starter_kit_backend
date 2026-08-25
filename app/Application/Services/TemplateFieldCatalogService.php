<?php

namespace App\Application\Services;

use App\Domain\Services\MergeContextInterface;

/**
 * The list of placeholders an author may use, in the shape the editor needs
 * to draw a field picker.
 *
 * This existed as data long before it existed as an endpoint:
 * MergeContextInterface::fields() already fed the preview's «label» filling,
 * but nothing ever exposed it, so an author had to type {first_name} from
 * memory. That is not a cosmetic gap — an unknown placeholder resolves to an
 * empty string with no error anywhere (PlaceholderResolverService::
 * substituteFields), so one typo silently ships an e-mail with a hole in it.
 *
 * Grouped rather than flat: the record fields come from whatever
 * MergeContext is wired in and change per deployment, while the tenant and
 * prompt groups are properties of the platform and do not.
 */
class TemplateFieldCatalogService
{
    public function __construct(
        private MergeContextInterface $mergeContext,
    ) {}

    /**
     * @return array<int, array{
     *     group: string,
     *     label: string,
     *     description: string,
     *     fields: array<int, array{key: string, label: string, placeholder: string}>
     * }>
     */
    public function groups(): array
    {
        return [
            [
                'group' => 'record',
                'label' => 'Record',
                'description' => 'Filled from the record the template is generated for.',
                'fields' => array_map(fn (array $field) => [
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'placeholder' => '{'.$field['key'].'}',
                ], $this->mergeContext->fields()),
            ],
            [
                'group' => 'tenant',
                'label' => 'Organization',
                'description' => 'Filled from the tenant automatically — available in every template.',
                'fields' => [
                    ['key' => 'company', 'label' => 'Company name', 'placeholder' => '{company}'],
                    ['key' => 'company_logo', 'label' => 'Company logo URL', 'placeholder' => '{company_logo}'],
                    ['key' => 'company_footer', 'label' => 'Company footer', 'placeholder' => '{company_footer}'],
                    ['key' => 'company_contact', 'label' => 'Company contact', 'placeholder' => '{company_contact}'],
                ],
            ],
        ];
    }

    /**
     * Every placeholder name that resolves to something, without braces.
     *
     * @return array<int, string>
     */
    public function knownKeys(): array
    {
        $keys = [];

        foreach ($this->groups() as $group) {
            foreach ($group['fields'] as $field) {
                $keys[] = $field['key'];
            }
        }

        return $keys;
    }
}
