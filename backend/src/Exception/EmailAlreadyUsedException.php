<?php

namespace App\Exception;

/**
 * Levée à l'inscription quand l'email existe déjà.
 * Le contrôleur la distingue d'une erreur de validation : 409 au lieu de 400.
 */
class EmailAlreadyUsedException extends \InvalidArgumentException
{
}
