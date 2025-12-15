// assets/js/cart.js

document.addEventListener('DOMContentLoaded', function() {
  // Initialize quantity controls
  initQuantityControls();
  
  // Initialize remove buttons
  initRemoveButtons();
  
  // Initialize checkout form validation
  initCheckoutValidation();
  
  // Update cart total on quantity change
  updateCartTotal();
});

function initQuantityControls() {
  // Quantity controls for cart page
  document.querySelectorAll('.quantity-controls').forEach(control => {
    const minusBtn = control.querySelector('.qty-btn.minus, [data-action="decrease"]');
    const plusBtn = control.querySelector('.qty-btn.plus, [data-action="increase"]');
    const input = control.querySelector('input[type="number"]');
    
    if (minusBtn && input) {
      minusBtn.addEventListener('click', () => {
        if (input.value > parseInt(input.min || 1)) {
          input.value = parseInt(input.value) - 1;
          updateCartItem(input);
        }
      });
    }
    
    if (plusBtn && input) {
      plusBtn.addEventListener('click', () => {
        if (input.value < parseInt(input.max || 99)) {
          input.value = parseInt(input.value) + 1;
          updateCartItem(input);
        }
      });
    }
    
    if (input) {
      input.addEventListener('change', () => {
        updateCartItem(input);
      });
      
      // Prevent entering non-numeric values
      input.addEventListener('keypress', (e) => {
        if (e.key === 'e' || e.key === 'E' || e.key === '+' || e.key === '-') {
          e.preventDefault();
        }
      });
    }
  });
}

function initRemoveButtons() {
  document.querySelectorAll('.remove-item').forEach(button => {
    button.addEventListener('click', function() {
      const cartId = this.dataset.cartId;
      const productName = this.closest('.cart-item')?.querySelector('.product-name')?.textContent || 'this item';
      
      if (confirm(`Remove ${productName} from cart?`)) {
        removeCartItem(cartId);
      }
    });
  });
}

function updateCartItem(inputElement) {
  const cartId = inputElement.dataset.cartId;
  const quantity = inputElement.value;
  
  // Show loading on the input
  inputElement.disabled = true;
  const originalValue = inputElement.value;
  
  fetch('api/cart.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `action=update&cart_id=${cartId}&quantity=${quantity}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Update the subtotal for this item
      updateItemSubtotal(cartId, quantity);
      // Update cart total
      updateCartTotal();
      // Update cart count
      updateCartCount();
      showNotification('Cart updated successfully', 'success');
    } else {
      showNotification(data.message || 'Failed to update cart', 'error');
      // Revert to original value
      inputElement.value = originalValue;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showNotification('Network error. Please try again.', 'error');
    inputElement.value = originalValue;
  })
  .finally(() => {
    inputElement.disabled = false;
  });
}

function removeCartItem(cartId) {
  const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
  
  if (!cartItem) return;
  
  // Add removing animation
  cartItem.style.opacity = '0.5';
  cartItem.style.pointerEvents = 'none';
  
  fetch('api/cart.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `action=remove&cart_id=${cartId}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Remove with animation
      cartItem.style.transform = 'translateX(100%)';
      cartItem.style.opacity = '0';
      
      setTimeout(() => {
        cartItem.remove();
        // Update cart total
        updateCartTotal();
        // Update cart count
        updateCartCount();
        // Check if cart is empty
        checkEmptyCart();
        showNotification('Item removed from cart', 'success');
      }, 300);
    } else {
      showNotification(data.message || 'Failed to remove item', 'error');
      cartItem.style.opacity = '1';
      cartItem.style.pointerEvents = 'auto';
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showNotification('Network error. Please try again.', 'error');
    cartItem.style.opacity = '1';
    cartItem.style.pointerEvents = 'auto';
  });
}

function updateItemSubtotal(cartId, quantity) {
  const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
  if (!cartItem) return;
  
  const priceElement = cartItem.querySelector('.price');
  const totalElement = cartItem.querySelector('.total');
  
  if (priceElement && totalElement) {
    // Extract price (remove currency symbol and commas)
    const priceText = priceElement.textContent.replace(/[^\d.]/g, '');
    const price = parseFloat(priceText);
    
    if (!isNaN(price)) {
      const subtotal = price * quantity;
      totalElement.textContent = formatPrice(subtotal);
    }
  }
}

function updateCartTotal() {
  const cartItems = document.querySelectorAll('.cart-item');
  let subtotal = 0;
  
  cartItems.forEach(item => {
    const totalElement = item.querySelector('.total');
    if (totalElement) {
      const totalText = totalElement.textContent.replace(/[^\d.]/g, '');
      const itemTotal = parseFloat(totalText);
      if (!isNaN(itemTotal)) {
        subtotal += itemTotal;
      }
    }
  });
  
  // Update subtotal in summary
  const subtotalElement = document.querySelector('.summary-row:nth-child(1) .amount');
  if (subtotalElement) {
    subtotalElement.textContent = formatPrice(subtotal);
  }
  
  // Update total in summary
  const totalElement = document.querySelector('.summary-row.total .amount');
  if (totalElement) {
    const shipping = 0; // Free shipping for campus pickup
    const total = subtotal + shipping;
    totalElement.textContent = formatPrice(total);
  }
}

function checkEmptyCart() {
  const cartItems = document.querySelectorAll('.cart-item');
  const cartContent = document.querySelector('.cart-content');
  const emptyCart = document.querySelector('.empty-cart');
  
  if (cartItems.length === 0 && cartContent && emptyCart) {
    cartContent.style.display = 'none';
    emptyCart.style.display = 'block';
  }
}

function formatPrice(amount) {
  return '₱' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function initCheckoutValidation() {
  const checkoutForm = document.querySelector('.checkout-form');
  if (!checkoutForm) return;
  
  checkoutForm.addEventListener('submit', function(e) {
    // Validate payment method
    const paymentMethod = this.querySelector('input[name="payment_method"]:checked');
    if (!paymentMethod) {
      e.preventDefault();
      showNotification('Please select a payment method', 'error');
      
      // Scroll to payment section
      const paymentSection = this.querySelector('.payment-options');
      if (paymentSection) {
        paymentSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      return;
    }
    
    // Validate pickup campus
    const pickupCampus = this.querySelector('#pickup_campus');
    if (pickupCampus && !pickupCampus.value) {
      e.preventDefault();
      showNotification('Please select a pickup campus', 'error');
      pickupCampus.focus();
      return;
    }
    
    // Validate terms
    const terms = this.querySelector('#terms');
    if (terms && !terms.checked) {
      e.preventDefault();
      showNotification('Please accept the terms and conditions', 'error');
      terms.focus();
      return;
    }
    
    // Show loading state
    const submitButton = this.querySelector('button[type="submit"]');
    if (submitButton) {
      const originalText = submitButton.innerHTML;
      submitButton.innerHTML = '<i class="ri-loader-4-line spin"></i> Processing...';
      submitButton.disabled = true;
      
      // Re-enable after 5 seconds in case of error
      setTimeout(() => {
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
      }, 5000);
    }
  });
  
  // Real-time validation for pickup campus
  const pickupCampus = checkoutForm.querySelector('#pickup_campus');
  if (pickupCampus) {
    pickupCampus.addEventListener('change', function() {
      if (this.value) {
        this.classList.remove('is-invalid');
      }
    });
  }
  
  // Real-time validation for payment method
  const paymentOptions = checkoutForm.querySelectorAll('input[name="payment_method"]');
  paymentOptions.forEach(option => {
    option.addEventListener('change', function() {
      if (this.checked) {
        // Remove invalid state from all payment options
        document.querySelectorAll('.payment-option').forEach(el => {
          el.classList.remove('is-invalid');
        });
      }
    });
  });
  
  // Real-time validation for terms
  const terms = checkoutForm.querySelector('#terms');
  if (terms) {
    terms.addEventListener('change', function() {
      if (this.checked) {
        this.classList.remove('is-invalid');
      }
    });
  }
}

// Add some animations to cart page
function addCartAnimations() {
  const cartItems = document.querySelectorAll('.cart-item');
  
  cartItems.forEach((item, index) => {
    item.style.animationDelay = `${index * 0.1}s`;
    item.classList.add('fade-in');
  });
}

// Initialize when page loads
if (document.querySelector('.cart-page')) {
  addCartAnimations();
  checkEmptyCart();
}