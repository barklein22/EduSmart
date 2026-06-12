function goTo(page) {
  window.location.href = page;
}

document.addEventListener("DOMContentLoaded", function () {
  const answerLabels = document.querySelectorAll(".answer-btn");

  answerLabels.forEach((label) => {
    const radio = label.querySelector('input[type="radio"]');

    if (!radio) return;

    radio.addEventListener("change", function () {
      const questionCard = label.closest(".question-card");
      if (!questionCard) return;

      const allLabels = questionCard.querySelectorAll(".answer-btn");
      allLabels.forEach((item) => item.classList.remove("selected-answer"));

      label.classList.add("selected-answer");
    });
  });
});