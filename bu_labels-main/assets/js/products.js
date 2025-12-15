// assets/js/products.js

document.addEventListener('DOMContentLoaded', function() {
  console.log('Products page loaded');
  
  // Add to wishlist functionality
  const wishlistButtons = document.querySelectorAll('.add-to-wishlist');
  wishlistButtons.forEach(button => {
    button.addEventListener('click', function(e) {
      e.preventDefault();
      const productId = this.getAttribute('data-product-id');
      
      // Check if user is logged in
      const isLoggedIn = document.body.classList.contains('logged-in');
      
      if (!isLoggedIn) {
        // Redirect to login page
        window.location.href = 'account.php?redirect=' + encodeURIComponent(window.location.href);
        return;
      }
      
      // Toggle button state
      const icon = this.querySelector('i');
      if (icon.classList.contains('ri-heart-fill')) {
        icon.classList.remove('ri-heart-fill');
        icon.classList.add('ri-heart-line');
        this.style.color = '';
        showNotification('Removed from wishlist', 'info');
      } else {
        icon.classList.remove('ri-heart-line');
        icon.classList.add('ri-heart-fill');
        this.style.color = 'var(--secondary-color)';
        showNotification('Added to wishlist', 'success');
      }
      
      // In a real app, you would make an AJAX call here
      // fetch('api/wishlist.php', { method: 'POST', body: JSON.stringify({product_id: productId}) })
    });
  });
  
  // Filter form enhancement
  const filterForm = document.querySelector('.filter-form');
  if (filterForm) {
    // Add change event listeners to selects
    const selects = filterForm.querySelectorAll('select');
    selects.forEach(select => {
      select.addEventListener('change', function() {
        // If both selects have values, submit the form
        setTimeout(() => {
          filterForm.submit();
        }, 100);
      });
    });
  }
  
  // Search form enhancement
  const searchForm = document.querySelector('.search-form');
  if (searchForm) {
    const searchInput = searchForm.querySelector('input[name="search"]');
    searchInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        searchForm.submit();
      }
    });
  }
});

// Helper function for notifications
function showNotification(message, type = 'info') {
  // Create notification element
  const notification = document.createElement('div');
  notification.className = `notification notification-${type}`;
  notification.innerHTML = `
    <div class="notification-content">
      <i class="ri-${type === 'success' ? 'check' : 'information'}-line"></i>
      <span>${message}</span>
    </div>
    <button class="notification-close">&times;</button>
  `;
  
  // Add to body
  document.body.appendChild(notification);
  
  // Add styles if not already present
  if (!document.querySelector('#notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
      .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 1rem 1.5rem;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow-hover);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
        max-width: 400px;
      }
      
      .notification-success {
        border-left: 4px solid var(--success);
      }
      
      .notification-info {
        border-left: 4px solid var(--info);
      }
      
      .notification-error {
        border-left: 4px solid var(--danger);
      }
      
      .notification-content {
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      .notification-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--dark-gray);
      }
      
      @keyframes slideIn {
        from {
          transform: translateX(100%);
          opacity: 0;
        }
        to {
          transform: translateX(0);
          opacity: 1;
        }
      }
    `;
    document.head.appendChild(style);
  }
  
  // Auto-remove after 3 seconds
  setTimeout(() => {
    notification.style.animation = 'slideOut 0.3s ease-out';
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 300);
  }, 3000);
  
  // Close button
  notification.querySelector('.notification-close').addEventListener('click', () => {
    notification.remove();
  });
}