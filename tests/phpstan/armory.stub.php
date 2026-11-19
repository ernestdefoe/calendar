<?php

/*
 * ArmoryCharacters reads ernestdefoe/armory's character model. Armory is
 * optional and brings its Battle.net stack with it, so it stays out of
 * require-dev; this is the model's shape for PHPStan. Never autoloaded;
 * extend.php only binds ArmoryCharacters while Armory is enabled.
 */

namespace ErnestDefoe\Armory;

use Flarum\Database\AbstractModel;

/**
 * @property int $user_id
 * @property string $name
 * @property string|null $class
 * @property string|null $spec
 * @property int $item_level
 * @property int|null $level
 * @property bool $is_main
 * @property bool $is_visible
 */
class ArmoryCharacter extends AbstractModel
{
}
