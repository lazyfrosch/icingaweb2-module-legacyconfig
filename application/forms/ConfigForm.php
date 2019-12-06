<?php

namespace Icinga\Module\Legacyconfig\Forms;

use Icinga\Forms\ConfigForm as BaseConfigForm;

class ConfigForm extends BaseConfigForm
{
    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $this->setSubmitLabel($this->translate('Save'));
    }

    /**
     * {@inheritdoc}
     */
    public function createElements(array $formData)
    {
        $this->addElement(
            'text',
            'base_config_path',
            array(
                'label'       => $this->translate('Config Path'),
                'description' => $this->translate('Location of configuration for Nagios or Icinga 1'),
            )
        );
    }
}
