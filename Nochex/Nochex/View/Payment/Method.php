<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace XLite\Module\Nochex\Nochex\View\Payment;


use XLite\Module\Nochex\Nochex\Model\Payment\Processor\Nochex;

/**
 * @inheritdoc
 */
class Method extends \XLite\View\Payment\Method implements \XLite\Base\IDecorator
{
    
    /**
     * @return bool
     */
    protected function isNochex()
    {
        return "Nochex";
    }
}