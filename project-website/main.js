
//////////////////////////////////////////////landing page//////////////////////////////////////////////

const menuBtn = document.getElementById("menu-btn");
const navLinks = document.getElementById("nav-links");
const menuBtnIcon = menuBtn.querySelector("i");

menuBtn.addEventListener("click", () => {
  navLinks.classList.toggle("open");

  const isOpen = navLinks.classList.contains("open");
  menuBtnIcon.setAttribute(
    "class",
    isOpen ? "ri-close-line" : "ri-menu-3-line"
  );
});

navLinks.addEventListener("click", () => {
  navLinks.classList.remove("open");
  menuBtnIcon.setAttribute("class", "ri-menu-line");
});

const scrollRevealOption = {
  distance: "50px",
  origin: "bottom",
  duration: 2000,

};

ScrollReveal().reveal(".header__container h1", {
  ...scrollRevealOption,
  delay: 1000,
});

ScrollReveal().reveal(".header__container h2", {
  ...scrollRevealOption,
  delay: 1500,
});
ScrollReveal().reveal(".header__container p", {
  ...scrollRevealOption,
  delay: 2000,
});
ScrollReveal().reveal(".header__container .header__btn", {
  ...scrollRevealOption,
  delay: 3000,
});
ScrollReveal().reveal(".socials li", {
  ...scrollRevealOption,
  delay: 4000,
  interval: 500,
});


//////////////////////////////////////////////feature category//////////////////////////////////////////////

const initSlider = () => {
  const imageList = document.querySelector(".slider-wrapper .image-list");
  const slideButtons = document.querySelectorAll(".slider-wrapper .slide-button");
  const maxScrollLeft = imageList.scrollWidth - imageList.clientWidth;

    // Slide images according to the slide button clicks
    slideButtons.forEach(button => {
    button.addEventListener("click", () => {
      const direction = button.id === "prev-slide" ? -1 : 1;
      const scrollAmount = imageList.clientWidth * direction;
      imageList.scrollBy({ left: scrollAmount, behavior: "smooth" });
    });
  });

  const handleSlideButtons = () => {
    slideButtons[0].style.display =
      imageList.scrollLeft <= 0 ? "none" : "block";

    slideButtons[1].style.display =
      imageList.scrollLeft >= maxScrollLeft ? "none" : "block";
  };

  imageList.addEventListener("scroll", () => {
    handleSlideButtons();
  });
}

window.addEventListener("load", initSlider);


//////////////////////////////////////////////special offer//////////////////////////////////////////////



// main.js — put this AFTER including ScrollReveal (or at end of <body>)
document.addEventListener("DOMContentLoaded", () => {
  if (typeof ScrollReveal === "undefined") {
    console.error("ScrollReveal not found — make sure <script src='https://unpkg.com/scrollreveal'></script> is included BEFORE main.js");
    return;
  }

  // Debug: make sure elements exist
  const imgEl = document.querySelector(".offer__image img");
  const h2El = document.querySelector(".offer__content h2");
  const h1El = document.querySelector(".offer__content h1");
  const pEl = document.querySelector(".offer__content p");
  const btnEl = document.querySelector(".offer__btn");
  const socials = document.querySelectorAll(".offer__socials li");

  console.group("ScrollReveal debug");
  console.log("offer__image img ->", !!imgEl, imgEl);
  console.log("offer__content h2 ->", !!h2El, h2El);
  console.log("offer__content h1 ->", !!h1El, h1El);
  console.log("offer__content p ->", !!pEl, pEl);
  console.log("offer__btn ->", !!btnEl, btnEl);
  console.log("offer__socials li count ->", socials.length);
  console.groupEnd();

  const srBase = {
    distance: "50px",
    origin: "bottom",
    duration: 1000,
    easing: "cubic-bezier(.2,.8,.2,1)",
    reset: true // important so animations happen when scrolling back down
  };

  // reveal image from right
  ScrollReveal().reveal(".offer__image img", { ...srBase, origin: "right", viewFactor: 0.2 });

  // content reveals
  ScrollReveal().reveal(".offer__content h2", { ...srBase, delay: 300, viewFactor: 0.15 });
  ScrollReveal().reveal(".offer__content h1", { ...srBase, delay: 600, viewFactor: 0.15 });
  ScrollReveal().reveal(".offer__content p", { ...srBase, delay: 900, viewFactor: 0.15 });
  ScrollReveal().reveal(".offer__btn", { ...srBase, delay: 1200, viewFactor: 0.15 });
  ScrollReveal().reveal(".offer__socials li", { ...srBase, delay: 1500, interval: 250, viewFactor: 0.1 });
});
