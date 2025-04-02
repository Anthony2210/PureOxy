/**
 * compte.js
 *
 * Ce script gère les interactions sur la page "compte.php", notamment :
 *  - La validation en temps réel des champs du formulaire d'inscription avec vérification AJAX.
 *  - L'activation ou la désactivation du bouton d'inscription en fonction de la validité des champs.
 *  - La gestion des fenêtres modales pour les commentaires et les demandes.
 *
 * Références :
 * - ChatGPT pour la structuration du code et la gestion des erreurs.
 *
 * Utilisation :
 * - Ce script s'exécute sur la page "compte.php" et améliore l'expérience utilisateur lors de l'inscription.
 *
 * Fichier placé dans le dossier script.
 */
$(document).ready(function() {
    var validUsername = false;
    var validEmail = false;
    var validPassword = false;
    var validConfirmPassword = false;

    function checkFormValidity() {
        if (validUsername && validEmail && validPassword && validConfirmPassword) {
            $('.btn-register').prop('disabled', false);
        } else {
            $('.btn-register').prop('disabled', true);
        }
    }

    // Validation AJAX du nom d'utilisateur
    $('#username').on('input', function() {
        var username = $(this).val();
        if (username.trim() === '') {
            $(this).removeClass('valid').addClass('invalid');
            $('#username-error').text('Le nom d\'utilisateur ne peut pas être vide.');
            validUsername = false;
            checkFormValidity();
        } else {
            $.ajax({
                url: '../fonctionnalites/check_username.php',
                method: 'POST',
                data: { username: username },
                dataType: 'json',
                success: function(response) {
                    if (response.exists) {
                        $('#username').removeClass('valid').addClass('invalid');
                        $('#username-error').text('Ce nom d\'utilisateur est déjà pris.');
                        validUsername = false;
                    } else {
                        $('#username').removeClass('invalid').addClass('valid');
                        $('#username-error').text('');
                        validUsername = true;
                    }
                    checkFormValidity();
                },
                error: function() {
                    $('#username').removeClass('valid').addClass('invalid');
                    $('#username-error').text('Erreur lors de la vérification du nom d\'utilisateur.');
                    validUsername = false;
                    checkFormValidity();
                }
            });
        }
    });

    // Validation AJAX de l'email
    $('#email').on('input', function() {
        var email = $(this).val();
        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (email.trim() === '') {
            $(this).removeClass('valid').addClass('invalid');
            $('#email-error').text('L\'email ne peut pas être vide.');
            validEmail = false;
            checkFormValidity();
        } else if (!emailPattern.test(email)) {
            $(this).removeClass('valid').addClass('invalid');
            $('#email-error').text('Format d\'email invalide.');
            validEmail = false;
            checkFormValidity();
        } else {
            $.ajax({
                url: '../fonctionnalites/check_email.php',
                method: 'POST',
                data: { email: email },
                dataType: 'json',
                success: function(response) {
                    if (response.exists) {
                        $('#email').removeClass('valid').addClass('invalid');
                        $('#email-error').text('Cet email est déjà utilisé.');
                        validEmail = false;
                    } else {
                        $('#email').removeClass('invalid').addClass('valid');
                        $('#email-error').text('');
                        validEmail = true;
                    }
                    checkFormValidity();
                },
                error: function() {
                    $('#email').removeClass('valid').addClass('invalid');
                    $('#email-error').text('Erreur lors de la vérification de l\'email.');
                    validEmail = false;
                    checkFormValidity();
                }
            });
        }
    });

    // Validation du mot de passe
    $('#password').on('input', function() {
        var password = $(this).val();
        var passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W]).{8,}$/;
        if (password.trim() === '') {
            $(this).removeClass('valid').addClass('invalid');
            $('#password-error').text('Le mot de passe ne peut pas être vide.');
            validPassword = false;
        } else if (!passwordPattern.test(password)) {
            $(this).removeClass('valid').addClass('invalid');
            $('#password-error').text('Le mot de passe doit contenir au moins 8 caractères, avec une majuscule, une minuscule, un chiffre et un caractère spécial.');
            validPassword = false;
        } else {
            $(this).removeClass('invalid').addClass('valid');
            $('#password-error').text('');
            validPassword = true;
        }
        var confirmPassword = $('#confirm_password').val();
        if (confirmPassword !== '') {
            if (password !== confirmPassword) {
                $('#confirm_password').removeClass('valid').addClass('invalid');
                $('#confirm-password-error').text('Les mots de passe ne correspondent pas.');
                validConfirmPassword = false;
            } else {
                $('#confirm_password').removeClass('invalid').addClass('valid');
                $('#confirm-password-error').text('');
                validConfirmPassword = true;
            }
        }
        checkFormValidity();
    });

    // Validation de la confirmation du mot de passe
    $('#confirm_password').on('input', function() {
        var confirmPassword = $(this).val();
        var password = $('#password').val();
        if (confirmPassword.trim() === '') {
            $(this).removeClass('valid').addClass('invalid');
            $('#confirm-password-error').text('Veuillez confirmer votre mot de passe.');
            validConfirmPassword = false;
        } else if (password !== confirmPassword) {
            $(this).removeClass('valid').addClass('invalid');
            $('#confirm-password-error').text('Les mots de passe ne correspondent pas.');
            validConfirmPassword = false;
        } else {
            $(this).removeClass('invalid').addClass('valid');
            $('#confirm-password-error').text('');
            validConfirmPassword = true;
        }
        checkFormValidity();
    });

    // Soumission du formulaire d'inscription via AJAX
    $('#registration-form').on('submit', function(e) {
        e.preventDefault();
        if (validUsername && validEmail && validPassword && validConfirmPassword) {
            var formData = {
                username: $('#username').val(),
                email: $('#email').val(),
                password: $('#password').val(),
                confirm_password: $('#confirm_password').val(),
                csrf_token: $('input[name="csrf_token"]').val()
            };
            $.ajax({
                url: '../fonctionnalites/register_user.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#registration-form')[0].reset();
                        $('.input-field').removeClass('valid');
                        $('.btn-register').prop('disabled', true);
                        $('#registration-form').hide();
                        $('#message-container').html('<div class="success-message">' + response.message + '</div>');
                        setTimeout(function() {
                            window.location.href = '../index.php';
                        }, 1000);
                    } else {
                        $('#message-container').html('<div class="error-message">' + response.message + '</div>');
                    }
                },
                error: function() {
                    $('#message-container').html('<div class="error-message">Erreur lors de la création du compte.</div>');
                }
            });
        } else {
            $('#message-container').html('<div class="error-message">Veuillez remplir correctement tous les champs.</div>');
        }
    });

    // Gestion de la fenêtre modale des commentaires
    const commentsModal = document.getElementById("comments-modal");
    const commentsBtn = document.getElementById("view-comments-button");
    const closeCommentsBtn = document.querySelector(".close-button-comments");

    if (commentsBtn) {
        commentsBtn.addEventListener("click", () => {
            commentsModal.style.display = "block";
            loadUserComments();
        });
    }
    if (closeCommentsBtn) {
        closeCommentsBtn.addEventListener("click", () => {
            commentsModal.style.display = "none";
        });
    }
    window.addEventListener("click", (event) => {
        if (event.target == commentsModal) {
            commentsModal.style.display = "none";
        }
    });

    function loadUserComments() {
        fetch('../fonctionnalites/load_user_comments.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('user-comments-list').innerHTML = data;
            })
            .catch(error => {
                console.error('Erreur lors du chargement des commentaires:', error);
                document.getElementById('user-comments-list').innerHTML = '<p>Une erreur est survenue.</p>';
            });
    }

    // Gestion de la fenêtre modale des demandes
    const requestsModal = document.getElementById("requests-modal");
    const requestsBtn = document.getElementById("view-requests-button");
    const closeRequestsBtn = document.getElementsByClassName("close-button")[0];

    if (requestsBtn) {
        requestsBtn.addEventListener("click", () => {
            requestsModal.style.display = "block";
            loadRequests();
        });
    }
    if (closeRequestsBtn) {
        closeRequestsBtn.addEventListener("click", () => {
            requestsModal.style.display = "none";
        });
    }
    window.addEventListener("click", (event) => {
        if (event.target == requestsModal) {
            requestsModal.style.display = "none";
        }
    });

    function loadRequests() {
        fetch('../fonctionnalites/load_requests.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('requests-list').innerHTML = data;
            })
            .catch(error => {
                console.error('Erreur lors du chargement des demandes:', error);
                document.getElementById('requests-list').innerHTML = '<p>Une erreur est survenue.</p>';
            });
    }

    // Fonction d'ouverture des onglets dans l'espace compte
    window.openTab = function(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("compte-tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
            tabcontent[i].classList.remove("active");
        }
        tablinks = document.getElementsByClassName("compte-tab-link");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        document.getElementById(tabName).style.display = "block";
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("active");
    };

    // Affichage par défaut de l'onglet connexion
    var connexionTab = document.getElementById("connexion");
    if (connexionTab) {
        connexionTab.style.display = "block";
    }

    // Initialisation des suggestions pour le champ de ville favorite
    if (typeof initializeSuggestions === 'function') {
        initializeSuggestions('favorite-city-input', 'suggestions-list', 'city_name_hidden', 'add-favorite-button');
    }

    $('#favorite-city-form').on('submit', function(event) {
        const hiddenInput = $('#city_name_hidden').val();
        if (!hiddenInput) {
            event.preventDefault();
            alert("Veuillez sélectionner une ville valide dans les suggestions.");
        }
    });
});
