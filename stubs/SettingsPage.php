<?php

namespace ProcessWire;

use RockMigrations\MagicPage;

function settings(): SettingsPage
{
  return wire()->pages->get('/settings');
}

class SettingsPage extends Page
{
  use MagicPage;

  const tpl = "settings";
  const prefix = "settings_";

  const field_phone = self::prefix . "phone";
  const field_email = 'email';
  const field_facebook = self::prefix . "facebook";
  const field_insta = self::prefix . "insta";
  const field_linkedin = self::prefix . "linkedin";
  const field_contact = self::prefix . "contact";
  const field_hours = self::prefix . "hours";
  const field_footerlinks = self::prefix . "footerlinks";

  public function init(): void
  {
    $this->wire('settings', settings());
  }

  /** magic */

  public function editForm(InputfieldWrapper $form): void
  {
    $rm = rockmigrations();

    // add top-bar wrapper
    $rm->wrapFields($form, [
      self::field_phone,
      self::field_email,
      self::field_facebook,
      self::field_insta,
      self::field_linkedin,
    ], [
      'label' => 'Top-Bar',
    ]);

    // add footer wrapper
    $rm->wrapFields($form, [
      self::field_contact,
      self::field_hours,
      self::field_footerlinks,
    ], [
      'label' => 'Footer',
    ]);
  }

  /** frontend */

  public function mail($link = false): string
  {
    $mail = $this->getFormatted('email');
    if ($link) return "mailto:$mail";
    return $mail;
  }

  public function phone($link = false): string
  {
    $phone = $this->getFormatted(self::field_phone);
    if ($link) {
      $link = str_replace([' ', '/', '-', '(', ')'], '', $phone);
      return "tel:$link";
    }
    return $phone;
  }

  /** backend */

  public function migrate()
  {
    $rm = $this->rockmigrations();
    $rm->migrate([
      'fields' => [],
      'templates' => [
        self::tpl => [
          'fields' => [
            // fields are added by macro and/or manually
          ],
          'icon' => 'cogs',
          'noSettings' => true,
          'noChildren' => true,
        ],
      ],
    ]);
    $rm->createPage(
      template: self::tpl,
      parent: 1,
      name: 'settings',
      title: 'Settings',
      status: ['hidden'],
    );
  }
}
