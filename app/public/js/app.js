/**
 * Comportements partagés de l'interface.
 *
 * Ce fichier existe pour éviter les gestionnaires d'évènements en attribut
 * (onclick="…", onchange="…") : ceux-ci sont bloqués par la politique de
 * sécurité de contenu (CSP), qui interdit l'exécution de JavaScript inline
 * afin de neutraliser les charges utiles XSS.
 */
(function () {
    'use strict';

    // <select data-auto-submit> : soumet le formulaire au changement.
    document.addEventListener('change', function (event) {
        var element = event.target;

        if (element instanceof Element && element.hasAttribute('data-auto-submit') && element.form) {
            element.form.submit();
        }
    });

    // <form data-confirm="message"> : demande confirmation avant envoi.
    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (form instanceof Element && form.hasAttribute('data-confirm')) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        }
    });

    // <button data-print> : ouvre la boîte d'impression du navigateur.
    document.addEventListener('click', function (event) {
        var element = event.target;

        if (element instanceof Element && element.closest('[data-print]')) {
            window.print();
        }
    });
})();
