<?php

namespace App\Notifications\Contracts;

/**
 * Marca uma notificação como "crítica": é a única categoria que pode ser
 * desviada para `admins.notification_email` em vez do e-mail de login
 * (ver Admin::routeNotificationForMail).
 *
 * Interface vazia de propósito — a semântica é inteiramente "encaminha-me para
 * o endereço de escalonamento", não há comportamento a implementar.
 *
 * DELIBERADAMENTE não implementada por PasswordResetNotification nem
 * PasswordChangedNotification: encaminhar recuperação de conta para um endereço
 * secundário e NÃO verificado é um vetor de tomada de conta. É precisamente
 * essa exclusão que torna seguro adiar a verificação do endereço — o campo não
 * concede acesso a nada. Se um dia essas notificações forem marcadas, a
 * verificação deixa de ser opcional.
 */
interface CriticalNotification
{
}
