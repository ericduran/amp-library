<?php
/*
 * Copyright 2016 Google
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Lullabot\AMP\Pass;

use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Validate\SValidationError;
use Lullabot\AMP\ActionTakenLine;
use Lullabot\AMP\ActionTakenType;

/**
 * Class StandardFixPass
 * @package Lullabot\AMP\Pass
 *
 * @todo make more sophisticated
 *
 */
class StandardFixPass extends BasePass
{
    protected $remove_attributes_for_codes = [
        ValidationErrorCode::INVALID_URL_PROTOCOL,
        ValidationErrorCode::INVALID_URL,
        ValidationErrorCode::INVALID_ATTR_VALUE,
        ValidationErrorCode::DISALLOWED_ATTR,
        ValidationErrorCode::DISALLOWED_PROPERTY_IN_ATTR_VALUE,
        ValidationErrorCode::INVALID_PROPERTY_VALUE_IN_ATTR_VALUE,
        ValidationErrorCode::MANDATORY_PROPERTY_MISSING_FROM_ATTR_VALUE,
        ValidationErrorCode::MISSING_URL,
        // @todo ValidationErrorCode::MUTUALLY_EXCLUSIVE_ATTRS?
    ];
    protected $remove_tags_for_codes = [
        ValidationErrorCode::WRONG_PARENT_TAG,
        ValidationErrorCode::DISALLOWED_TAG,
        // @todo ValidationErrorCode::DUPLICATE_UNIQUE_TAG?
    ];

    public function pass()
    {
        /** @var \DOMElement $last_rem_dom_tag_for_attr */
        $last_rem_dom_tag_for_attr = null;
        /** @var \DOMElement $last_rem_dom_tag */
        $last_rem_dom_tag = null;
        $last_dom_attr_name = '';

        /** @var SValidationError $error */
        foreach ($this->validation_result->errors as $error) {
            if (empty($error->dom_tag)) {
                continue;
            }

            $tag_name = $error->dom_tag->tagName;
            $context_string = $this->getContextString($error->dom_tag);

            if (in_array($error->code, $this->remove_attributes_for_codes) && !empty($error->attr_name)) {
                // No point removing attribute if we already removed the tag!
                if (!empty($last_rem_dom_tag) && $last_rem_dom_tag->isSameNode($error->dom_tag)) {
                    continue;
                }

                // Don't remove the same attribute again and again
                if (!empty($last_rem_dom_tag_for_attr) &&
                    $error->dom_tag->isSameNode($last_rem_dom_tag_for_attr) &&
                    $last_dom_attr_name === $error->attr_name
                ) {
                    continue;
                }

                // Remove the offending attribute
                $last_dom_attr_name = $error->attr_name;
                $last_rem_dom_tag_for_attr = $error->dom_tag;
                $error->dom_tag->removeAttribute($error->attr_name);
                $this->addActionTaken(new ActionTakenLine("$tag_name.$error->attr_name", ActionTakenType::ATTRIBUTE_REMOVED, $error->line, $context_string));
            }

            if (in_array($error->code, $this->remove_tags_for_codes) && !empty($error->dom_tag)) {
                // Don't remove the same tag again and again
                if (!empty($last_rem_dom_tag) && $error->dom_tag->isSameNode($last_rem_dom_tag)) {
                    continue;
                }

                $last_rem_dom_tag = $error->dom_tag;
                // Remove the offending tag
                $error->dom_tag->parentNode->removeChild($error->dom_tag);
                $this->addActionTaken(new ActionTakenLine($tag_name, ActionTakenType::TAG_REMOVED, $error->line, $context_string));
            }
        }

        return $this->warnings;
    }
}
