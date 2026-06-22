

let currentQuestion = 0;
let answers = {};

async function loadQuiz(quizId) {
    // Chargement via API (à implémenter selon structure)
    console.log('Chargement quiz', quizId);
}

function addQuestion() {
    const container = document.getElementById('questions-container');
    const qId = Date.now();
    container.innerHTML += `
        <div class="question-block" data-id="${qId}">
            <input type="text" placeholder="Question" class="question-text">
            <button onclick="addChoice(${qId})">Ajouter choix</button>
        </div>
    `;
}

function submitQuiz(quizId) {
    postData('../api/quiz.php', { action: 'submit', quiz_id: quizId, answers: answers })
        .then(res => {
            showToast(res.message, res.passed ? 'success' : 'error');
        });
}