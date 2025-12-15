// assets/js/main.js

//////////////////////////////////////////////landing page//////////////////////////////////////////////

const menuBtn = document.getElementById("menu-btn");
const navLinks = document.getElementById("nav-links");
const menuBtnIcon = menuBtn?.querySelector("i");

// Mobile menu toggle for OLD navigation (if it exists)
if (menuBtn && navLinks) {
  menuBtn.addEventListener("click", () => {
    navLinks.classList.toggle("open");

    const isOpen = navLinks.classList.contains("open");
    if (menuBtnIcon) {
      menuBtnIcon.setAttribute(
        "class",
        isOpen ? "ri-close-line" : "ri-menu-3-line"
      );
    }
  });

  navLinks.addEventListener("click", () => {
    navLinks.classList.remove("open");
    if (menuBtnIcon) {
      menuBtnIcon.setAttribute("class", "ri-menu-line");
    }
  });
}

////////////////////////////////////////////// NEW MOBILE MENU FOR FIXED HEADER //////////////////////////////////////////////

document.addEventListener('DOMContentLoaded', function() {
    const newMenuBtn = document.getElementById('menu-btn');
    const navLinksContainer = document.querySelector('.nav-links-container');
    
    if (newMenuBtn && navLinksContainer) {
        newMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            navLinksContainer.classList.toggle('active');
            const icon = newMenuBtn.querySelector('i');
            if (navLinksContainer.classList.contains('active')) {
                icon.classList.remove('ri-menu-3-line');
                icon.classList.add('ri-close-line');
            } else {
                icon.classList.remove('ri-close-line');
                icon.classList.add('ri-menu-3-line');
            }
        });
        
        // Close menu when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && 
                navLinksContainer && 
                navLinksContainer.classList.contains('active') &&
                !newMenuBtn.contains(event.target) && 
                !navLinksContainer.contains(event.target)) {
                navLinksContainer.classList.remove('active');
                const icon = newMenuBtn.querySelector('i');
                icon.classList.remove('ri-close-line');
                icon.classList.add('ri-menu-3-line');
            }
        });
    }
    
    // Close mobile menu when clicking a link
    const navLinksItems = document.querySelectorAll('.nav__links a');
    navLinksItems.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768 && navLinksContainer) {
                navLinksContainer.classList.remove('active');
                const icon = newMenuBtn ? newMenuBtn.querySelector('i') : null;
                if (icon) {
                    icon.classList.remove('ri-close-line');
                    icon.classList.add('ri-menu-3-line');
                }
            }
        });
    });
    
    // Fix header spacing
    fixHeaderSpacing();
});

////////////////////////////////////////////// HEADER SPACING FIX //////////////////////////////////////////////

function fixHeaderSpacing() {
    const nav = document.querySelector('nav');
    const body = document.body;
    
    if (!nav) return;
    
    // Calculate header height
    const headerHeight = nav.offsetHeight;
    
    // Apply padding to body
    if (body) {
        body.style.paddingTop = headerHeight + 'px';
    }
    
    // Add CSS variable for other uses
    document.documentElement.style.setProperty('--header-height', headerHeight + 'px');
    
    // Optional: Log for debugging
    // console.log('Header height set to:', headerHeight + 'px');
}

// Run on resize
window.addEventListener('resize', fixHeaderSpacing);
window.addEventListener('load', fixHeaderSpacing);

////////////////////////////////////////////// Scroll animations //////////////////////////////////////////////

const scrollRevealOption = {
  distance: "50px",
  origin: "bottom",
  duration: 1000,
};

// Initialize ScrollReveal if available
if (typeof ScrollReveal !== 'undefined') {
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
}

////////////////////////////////////////////// feature category //////////////////////////////////////////////

const initSlider = () => {
  const imageList = document.querySelector(".slider-wrapper .image-list");
  const slideButtons = document.querySelectorAll(".slider-wrapper .slide-button");
  
  if (!imageList || slideButtons.length === 0) return;

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
    if (slideButtons[0]) {
      slideButtons[0].style.display =
        imageList.scrollLeft <= 0 ? "none" : "block";
    }
    
    if (slideButtons[1]) {
      slideButtons[1].style.display =
        imageList.scrollLeft >= maxScrollLeft ? "none" : "block";
    }
  };

  imageList.addEventListener("scroll", () => {
    handleSlideButtons();
  });

  // Initial check
  handleSlideButtons();
}

// Initialize slider on window load
window.addEventListener("load", initSlider);

////////////////////////////////////////////// special offer //////////////////////////////////////////////

if (typeof ScrollReveal !== 'undefined') {
  const srBase = {
    distance: "50px",
    origin: "bottom",
    duration: 1000,
    easing: "cubic-bezier(.2,.8,.2,1)",
    reset: true
  };

  ScrollReveal().reveal(".offer__image img", { 
    ...srBase, 
    origin: "right", 
    viewFactor: 0.2 
  });

  ScrollReveal().reveal(".offer__content h2", { 
    ...srBase, 
    delay: 300, 
    viewFactor: 0.15 
  });
  
  ScrollReveal().reveal(".offer__content h1", { 
    ...srBase, 
    delay: 600, 
    viewFactor: 0.15 
  });
  
  ScrollReveal().reveal(".offer__content p", { 
    ...srBase, 
    delay: 900, 
    viewFactor: 0.15 
  });
  
  ScrollReveal().reveal(".offer__btn", { 
    ...srBase, 
    delay: 1200, 
    viewFactor: 0.15 
  });
}

////////////////////////////////////////////// general utilities //////////////////////////////////////////////

// Add to cart functionality
document.addEventListener('DOMContentLoaded', function() {
  // Add to cart buttons
  const addToCartButtons = document.querySelectorAll('.add-to-cart');
  
  addToCartButtons.forEach(button => {
    button.addEventListener('click', function() {
      const productId = this.dataset.productId;
      const size = document.querySelector('#selected-size')?.value || '';
      const quantity = document.querySelector('#quantity')?.value || 1;
      
      // Show loading state
      const originalText = this.innerHTML;
      this.innerHTML = '<i class="ri-loader-4-line spin"></i> Adding...';
      this.disabled = true;
      
      // Send AJAX request
      fetch('api/cart.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add&product_id=${productId}&size=${size}&quantity=${quantity}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update cart count
          updateCartCount();
          
          // Show success message
          showNotification('Product added to cart!', 'success');
          
          // Update button text if item was already in cart
          if (this.querySelector('.ri-shopping-cart-line')) {
            this.innerHTML = '<i class="ri-shopping-cart-line"></i> Update Cart';
          }
        } else {
          showNotification(data.message || 'Failed to add to cart', 'error');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showNotification('Network error. Please try again.', 'error');
      })
      .finally(() => {
        // Restore button state
        this.innerHTML = originalText;
        this.disabled = false;
      });
    });
  });

  // Add to wishlist buttons
  const wishlistButtons = document.querySelectorAll('.add-to-wishlist');
  
  wishlistButtons.forEach(button => {
    button.addEventListener('click', function() {
      const productId = this.dataset.productId;
      
      fetch('api/wishlist.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add&product_id=${productId}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showNotification('Added to wishlist!', 'success');
          this.innerHTML = '<i class="ri-heart-fill"></i>';
        } else {
          if (data.redirect) {
            window.location.href = data.redirect;
          } else {
            showNotification(data.message || 'Failed to add to wishlist', 'error');
          }
        }
      });
    });
  });

  // Buy now buttons
  const buyNowButtons = document.querySelectorAll('.buy-now');
  
  buyNowButtons.forEach(button => {
    button.addEventListener('click', function() {
      const productId = this.dataset.productId;
      const size = document.querySelector('#selected-size')?.value || '';
      const quantity = document.querySelector('#quantity')?.value || 1;
      
      // Add to cart first
      fetch('api/cart.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add&product_id=${productId}&size=${size}&quantity=${quantity}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Redirect to checkout
          window.location.href = 'checkout.php';
        } else {
          showNotification(data.message || 'Failed to process order', 'error');
        }
      });
    });
  });
});

// Update cart count
function updateCartCount() {
  fetch('api/cart.php?action=count')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const cartCountElements = document.querySelectorAll('.cart-count');
        cartCountElements.forEach(element => {
          element.textContent = data.count;
        });
      }
    });
}

// Show notification
function showNotification(message, type = 'info') {
  // Remove existing notification
  const existingNotification = document.querySelector('.notification');
  if (existingNotification) {
    existingNotification.remove();
  }

  // Create notification element
  const notification = document.createElement('div');
  notification.className = `notification notification-${type}`;
  notification.innerHTML = `
    <i class="ri-${type === 'success' ? 'checkbox-circle-line' : 'error-warning-line'}"></i>
    <span>${message}</span>
    <button class="notification-close">&times;</button>
  `;

  // Add to page
  document.body.appendChild(notification);

  // Show with animation
  setTimeout(() => {
    notification.classList.add('show');
  }, 10);

  // Auto remove after 5 seconds
  setTimeout(() => {
    notification.classList.remove('show');
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 300);
  }, 5000);

  // Close button
  notification.querySelector('.notification-close').addEventListener('click', () => {
    notification.classList.remove('show');
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 300);
  });
}

// Add notification styles (only once)
if (!document.querySelector('#notification-styles')) {
  const notificationStyles = document.createElement('style');
  notificationStyles.id = 'notification-styles';
  notificationStyles.textContent = `
    .notification {
      position: fixed;
      top: 100px;
      right: 20px;
      background: white;
      border-radius: 8px;
      padding: 1rem 1.5rem;
      box-shadow: 0 5px 20px rgba(0,0,0,0.15);
      display: flex;
      align-items: center;
      gap: 0.75rem;
      z-index: 1002;
      transform: translateX(400px);
      transition: transform 0.3s ease;
      max-width: 350px;
    }
    
    .notification.show {
      transform: translateX(0);
    }
    
    .notification-success {
      border-left: 4px solid #28a745;
    }
    
    .notification-success i {
      color: #28a745;
    }
    
    .notification-error {
      border-left: 4px solid #dc3545;
    }
    
    .notification-error i {
      color: #dc3545;
    }
    
    .notification-close {
      background: none;
      border: none;
      font-size: 1.25rem;
      color: #6c757d;
      cursor: pointer;
      margin-left: auto;
      padding: 0;
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
    }
    
    .notification-close:hover {
      background: #f8f9fa;
    }
    
    .spin {
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  `;

  document.head.appendChild(notificationStyles);
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
  // Add form validation to all forms
  const forms = document.querySelectorAll('form:not(.no-validate)');
  
  forms.forEach(form => {
    form.addEventListener('submit', function(e) {
      const requiredFields = this.querySelectorAll('[required]');
      let isValid = true;
      let firstInvalidField = null;
      
      requiredFields.forEach(field => {
        if (!field.value.trim()) {
          isValid = false;
          field.classList.add('is-invalid');
          
          if (!firstInvalidField) {
            firstInvalidField = field;
          }
        } else {
          field.classList.remove('is-invalid');
        }
      });
      
      if (!isValid) {
        e.preventDefault();
        e.stopPropagation();
        
        // Scroll to first invalid field
        if (firstInvalidField) {
          firstInvalidField.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
          });
          firstInvalidField.focus();
        }
        
        showNotification('Please fill in all required fields', 'error');
      }
    });
  });
  
  // Remove invalid class when user starts typing
  document.querySelectorAll('[required]').forEach(field => {
    field.addEventListener('input', function() {
      if (this.value.trim()) {
        this.classList.remove('is-invalid');
      }
    });
  });
});

// Add invalid field styles (only once)
if (!document.querySelector('#invalid-field-styles')) {
  const invalidFieldStyles = document.createElement('style');
  invalidFieldStyles.id = 'invalid-field-styles';
  invalidFieldStyles.textContent = `
    .is-invalid {
      border-color: #dc3545 !important;
      box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
    }
  `;

  document.head.appendChild(invalidFieldStyles);
}