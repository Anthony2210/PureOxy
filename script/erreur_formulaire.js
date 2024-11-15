document.querySelector('form').addEventListener('submit', function(e) {
    var ville = document.querySelector('input[name="ville"]');

    if (ville.value.trim() === '') {
        e.preventDefault();  // Empêche l'envoi du formulaire
        ville.classList.add('error');
        alert('Veuillez entrer un nom de ville.');
    }
});
