import app from 'flarum/admin/app';

app.initializers.add('dshovchko/flarum-image-migrate', () => {
  app.extensionData
    .for('dshovchko-image-migrate')
    .registerSetting(() =>
      m('div', { className: 'Form-group' }, [
        m('label', app.translator.trans('dshovchko-image-migrate.admin.backend_section_heading')),
        m('p', app.translator.trans('dshovchko-image-migrate.admin.backend_section_help')),
      ])
    )
    .registerSetting({
      setting: 'dshovchko-image-migrate.snapgrab_base_url',
      type: 'text',
      label: app.translator.trans('dshovchko-image-migrate.admin.backend_base_url_label'),
      help: app.translator.trans('dshovchko-image-migrate.admin.backend_base_url_help'),
      placeholder: 'https://api.example.com',
    })
    .registerSetting({
      setting: 'dshovchko-image-migrate.snapgrab_env',
      type: 'select',
      label: app.translator.trans('dshovchko-image-migrate.admin.backend_env_label'),
      options: {
        production: app.translator.trans('dshovchko-image-migrate.admin.backend_env_production'),
        integration: app.translator.trans('dshovchko-image-migrate.admin.backend_env_integration'),
      },
      default: 'production',
    })
    .registerSetting({
      setting: 'dshovchko-image-migrate.snapgrab_api_key',
      type: 'text',
      label: app.translator.trans('dshovchko-image-migrate.admin.backend_api_key_label'),
      help: app.translator.trans('dshovchko-image-migrate.admin.backend_api_key_help'),
      placeholder: 'snapgrab-prod-key',
    })
    .registerSetting({
      setting: 'dshovchko-image-migrate.allowed_origins',
      type: 'text',
      label: app.translator.trans('dshovchko-image-migrate.admin.allowed_origins_label'),
      help: app.translator.trans('dshovchko-image-migrate.admin.allowed_origins_help'),
      placeholder: 'example.com, cdn.example.com',
    })
    .registerSetting({
      setting: 'dshovchko-image-migrate.scheduled_enabled',
      type: 'boolean',
      label: app.translator.trans('dshovchko-image-migrate.admin.scheduled_enabled_label'),
      help: app.translator.trans('dshovchko-image-migrate.admin.scheduled_enabled_help'),
    })
    .registerSetting({
      setting: 'dshovchko-image-migrate.scheduled_frequency',
      type: 'select',
      label: app.translator.trans('dshovchko-image-migrate.admin.scheduled_frequency_label'),
      options: {
        daily: app.translator.trans('dshovchko-image-migrate.admin.frequency_daily'),
        weekly: app.translator.trans('dshovchko-image-migrate.admin.frequency_weekly'),
        monthly: app.translator.trans('dshovchko-image-migrate.admin.frequency_monthly'),
      },
      default: 'weekly',
    })
    .registerSetting({
      setting: 'dshovchko-image-migrate.scheduled_emails',
      type: 'text',
      label: app.translator.trans('dshovchko-image-migrate.admin.scheduled_emails_label'),
      help: app.translator.trans('dshovchko-image-migrate.admin.scheduled_emails_help'),
      placeholder: 'admin@example.com, moderator@example.com',
    });
});
