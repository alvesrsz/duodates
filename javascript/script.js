document.addEventListener('DOMContentLoaded', () => {
    const cardGroups = document.querySelectorAll('.card-group');
    const leftArrow = document.querySelector('.left-arrow');
    const rightArrow = document.querySelector('.right-arrow');
    let currentGroupIndex = 0;

    function updateCards() {
        cardGroups.forEach((group, index) => {
            if (index === currentGroupIndex) {
                group.classList.add('active');
                group.style.display = 'flex';
            } else {
                group.classList.remove('active');
                group.style.display = 'none';
            }
        });
    }

    rightArrow.addEventListener('click', () => {
        currentGroupIndex = (currentGroupIndex + 1) % cardGroups.length;
        updateCards();
    });

    leftArrow.addEventListener('click', () => {
        currentGroupIndex = (currentGroupIndex - 1 + cardGroups.length) % cardGroups.length;
        updateCards();
    });
});


document.addEventListener('DOMContentLoaded', () => {
    const showMoreBtn = document.getElementById('show-more-btn');
    const hiddenCards = document.getElementById('hidden-cards');

    // Apenas executa se o botão e o container existirem na página
    if (showMoreBtn && hiddenCards) {
        showMoreBtn.addEventListener('click', () => {
            // Torna o contêiner visível
            hiddenCards.style.display = 'grid';
            
            // Oculta o botão "ver mais" após o clique
            showMoreBtn.style.display = 'none';
        });
    }
});