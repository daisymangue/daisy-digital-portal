<?php

namespace Database\Seeders;

use App\Models\CategoryQuestion;
use App\Models\Package;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Loads the service catalogue and packages from database/seeders/data.
 * Safe to run more than once: existing rows are matched by name and left alone.
 */
class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = json_decode(file_get_contents(__DIR__.'/data/services.json'), true);
        $questions = $this->questions();

        foreach ($catalogue as $i => $cat) {
            $category = ServiceCategory::firstOrCreate(
                ['slug' => Str::slug($cat['name'])],
                ['name' => $cat['name'], 'description' => $this->descriptions()[$cat['name']] ?? null, 'sort_order' => $i + 1],
            );

            foreach ($cat['services'] as $j => $s) {
                Service::firstOrCreate(
                    ['service_category_id' => $category->id, 'name' => $s['name']],
                    ['pricing_type' => $s['pricing_type'], 'price_cents' => $s['price_cents'], 'is_from' => $s['is_from'], 'unit' => $s['unit'], 'sort_order' => $j + 1],
                );
            }

            if ($category->questions()->count() === 0) {
                foreach ($questions[$cat['name']] ?? [] as $k => $q) {
                    CategoryQuestion::create([
                        'service_category_id' => $category->id,
                        'label' => $q[0],
                        'type' => $q[1],
                        'options' => $q[2] ?? null,
                        'is_required' => $q[3] ?? false,
                        'sort_order' => $k + 1,
                    ]);
                }
            }
        }

        $packages = json_decode(file_get_contents(__DIR__.'/data/packages.json'), true);
        $markFeatured = ! Package::where('is_featured', true)->exists();
        foreach ($packages as $i => $p) {
            Package::firstOrCreate(
                ['name' => $p['name']],
                ['price_cents' => $p['price_cents'], 'is_from' => $p['is_from'], 'unit' => $p['unit'], 'includes' => $p['includes'], 'sort_order' => $i + 1],
            );
        }
        if (! Service::where('is_popular', true)->exists()) {
            Service::whereIn('name', ['Logo Design', 'Professional Business Email Setup', 'Website Redesign', 'Online Store Setup', 'Google Business Profile Setup', 'Website Health Check', 'Social Media Post Design', 'Microsoft 365 Setup'])
                ->update(['is_popular' => true]);
        }
        if ($markFeatured) {
            Package::where('name', 'Business Essentials Package')->update(['is_featured' => true]);
        }
    }

    private function descriptions(): array
    {
        return [
            'Email Services' => 'Business email setup, migration and fixes',
            'Website Design & Development' => 'New websites, online stores and redesigns',
            'Website Hosting & Domains' => 'Domains, hosting, SSL and backups',
            'Website Support & Maintenance' => 'Updates, fixes and monthly care',
            'Website SEO & Analytics' => 'Get found on Google and track results',
            'Website Integrations' => 'WhatsApp, payments, bookings and more',
            'Computer Setup & Repairs' => 'Setup, upgrades, virus removal and repairs',
            'IT Support' => 'Remote and on-site help for your team',
            'Microsoft 365 & Google Workspace' => 'Setup, migration and administration',
            'Cloud Services' => 'Cloud storage, backup and file sharing',
            'Cybersecurity' => 'Protect your devices, email and data',
            'Digital Marketing' => 'Ads, social media and campaigns',
            'Graphic Design' => 'Logos, branding and marketing designs',
            'Document Services' => 'Typing, formatting, PDFs and templates',
            'Business Productivity Services' => 'Templates, workflows and digital filing',
        ];
    }

    /** Each question: [label, type, options, required]. Edit these in Admin > Services. */
    private function questions(): array
    {
        $platforms = ['WordPress', 'Shopify', 'Wix', 'Custom built', 'Not sure'];
        $where = ['Remote', 'On-site', 'Not sure'];

        return [
            'Email Services' => [
                ['What is your domain name, if you have one?', 'text'],
                ['How many mailboxes do you need?', 'text'],
                ['Which email provider do you use or prefer?', 'select', ['Microsoft 365', 'Google Workspace', 'Hosting provider email', 'Not sure']],
            ],
            'Website Design & Development' => [
                ['Do you have an existing website? If yes, add the address.', 'text'],
                ['Do you have your logo, text and photos ready?', 'select', ['Yes, all ready', 'Some of it', 'Not yet']],
                ['List two or three websites you like.', 'textarea'],
            ],
            'Website Hosting & Domains' => [
                ['What is your domain name?', 'text'],
                ['Who is your current hosting provider, if any?', 'text'],
            ],
            'Website Support & Maintenance' => [
                ['What is your website address?', 'text', null, true],
                ['What platform is it built on?', 'select', $platforms],
                ['Describe the problem or the change you need.', 'textarea'],
            ],
            'Website SEO & Analytics' => [
                ['What is your website address?', 'text', null, true],
                ['Which towns or areas do you want to be found in?', 'text'],
            ],
            'Website Integrations' => [
                ['What is your website address?', 'text', null, true],
                ['What platform is it built on?', 'select', $platforms],
            ],
            'Computer Setup & Repairs' => [
                ['What device is it? Brand and model if you know.', 'text'],
                ['Would you like remote help or an on-site visit?', 'select', $where],
                ['Which area are you based in?', 'text'],
            ],
            'IT Support' => [
                ['How many people or devices need support?', 'text'],
                ['Would you like remote help or an on-site visit?', 'select', $where],
            ],
            'Microsoft 365 & Google Workspace' => [
                ['How many users do you need?', 'text'],
                ['Do you already have an account?', 'yesno'],
            ],
            'Cloud Services' => [
                ['Which cloud service do you use or prefer?', 'select', ['Google Drive', 'OneDrive', 'Dropbox', 'Not sure']],
                ['Roughly how much data do you have?', 'text'],
            ],
            'Cybersecurity' => [
                ['How many devices need protection?', 'text'],
                ['Is this urgent, for example an active virus?', 'yesno'],
            ],
            'Digital Marketing' => [
                ['Which platforms matter most to you?', 'text'],
                ['What is your main goal?', 'select', ['More sales', 'More leads', 'Brand awareness', 'Not sure']],
                ['What is your monthly advertising budget? (optional)', 'text'],
            ],
            'Graphic Design' => [
                ['What is the design for, and where will it be used?', 'textarea'],
                ['Do you have a logo and brand colours?', 'yesno'],
                ['When do you need it by?', 'text'],
            ],
            'Document Services' => [
                ['How many documents or pages?', 'text'],
                ['When do you need it by?', 'text'],
            ],
            'Business Productivity Services' => [
                ['What would you like to improve or automate?', 'textarea'],
                ['Which tools do you use now?', 'text'],
            ],
        ];
    }
}
