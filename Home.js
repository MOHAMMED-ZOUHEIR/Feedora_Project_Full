/**
 * Feedora - Main JavaScript
 * This file handles all interactive elements of the Feedora homepage
 */

// Hide loading screen once page is fully loaded
window.addEventListener('load', function() {
  // Hide loading screen with a slight delay for smooth transition
  setTimeout(() => {
    const loadingScreen = document.getElementById('loading-screen');
    if (loadingScreen) {
      loadingScreen.style.opacity = '0';
      setTimeout(() => {
        loadingScreen.style.display = 'none';
      }, 500);
    }
  }, 800);

  // Fallback in case of errors
  try {
  // Mobile menu functionality with improved accessibility
  const menuToggle = document.querySelector('.menu-toggle');
  const mobileMenu = document.querySelector('.mobile-menu');
  const closeMenuButton = document.querySelector('.close-menu-button');
  
  function openMobileMenu() {
    if (mobileMenu && menuToggle) {
      mobileMenu.classList.add('active');
      mobileMenu.setAttribute('aria-hidden', 'false');
      menuToggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
  }
  
  function closeMobileMenu() {
    if (mobileMenu && menuToggle) {
      mobileMenu.classList.remove('active');
      mobileMenu.setAttribute('aria-hidden', 'true');
      menuToggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = ''; // Restore scrolling
    }
  }
  
  // Toggle menu when clicking the menu button
  if (menuToggle) {
    menuToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      if (mobileMenu.classList.contains('active')) {
        closeMobileMenu();
      } else {
        openMobileMenu();
      }
    });
  }
  
  // Close menu when clicking the close button
  if (closeMenuButton) {
    closeMenuButton.addEventListener('click', closeMobileMenu);
  }
  
  // Close mobile menu when clicking outside
  document.addEventListener('click', function(event) {
    if (mobileMenu && 
        mobileMenu.classList.contains('active') && 
        !mobileMenu.contains(event.target) && 
        event.target !== menuToggle) {
      closeMobileMenu();
    }
  });
  
  // Close mobile menu when pressing Escape key
  document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('active')) {
      closeMobileMenu();
    }
  });
  
  // Chefs Slider Functionality - Completely rewritten for reliability
  (function initializeChefSlider() {
    const chefsSlider = document.querySelector('#chefs-slider');
    const chefsSliderPrev = document.querySelector('.chefs-slider-prev');
    const chefsSliderNext = document.querySelector('.chefs-slider-next');
    const chefsDots = document.querySelectorAll('.chefs-dot');
    const chefCards = document.querySelectorAll('.chef-card');
    const followButtons = document.querySelectorAll('.follow-button');
    
    // Check if slider exists
    if (!chefsSlider || !chefCards.length) {
      console.log('Chef slider not found or no chef cards');
      return;
    }
    
    console.log(`Found chef slider with ${chefCards.length} cards`);
    
    // Initialize slider state
    let currentIndex = 0;
    let autoSlideInterval = null;
    
    // Apply initial styling to slider and cards
    chefsSlider.style.display = 'flex';
    chefsSlider.style.width = '100%';
    chefsSlider.style.transition = 'transform 0.5s ease';
    chefsSlider.style.overflow = 'visible';
    
    // Normalize card dimensions for consistent display
    normalizeCardDimensions();
    
    // Get the number of visible cards based on viewport width
    function getVisibleCardsCount() {
      const viewportWidth = window.innerWidth;
      if (viewportWidth <= 576) {
        return 1; // Mobile phones
      } else if (viewportWidth <= 768) {
        return 1; // Small tablets
      } else if (viewportWidth <= 992) {
        return 2; // Tablets
      } else if (viewportWidth <= 1200) {
        return 2; // Small desktops
      } else {
        return 3; // Large desktops
      }
    }
    
    // Normalize card dimensions and make chef images circular
    function normalizeCardDimensions() {
      // Find the tallest image and info container
      let maxImageHeight = 0;
      let maxInfoHeight = 0;
      let maxCardHeight = 0;
      
      // First pass: measure all elements
      chefCards.forEach(card => {
        const imageContainer = card.querySelector('.chef-image-container');
        const infoContainer = card.querySelector('.chef-info-container');
        
        if (imageContainer) {
          maxImageHeight = Math.max(maxImageHeight, imageContainer.offsetHeight);
        }
        
        if (infoContainer) {
          maxInfoHeight = Math.max(maxInfoHeight, infoContainer.offsetHeight);
        }
        
        maxCardHeight = Math.max(maxCardHeight, card.offsetHeight);
      });
      
      // Ensure minimum dimensions for better appearance
      maxImageHeight = Math.max(maxImageHeight, 180);
      
      console.log(`Normalizing chef cards: image=${maxImageHeight}px, info=${maxInfoHeight}px, total=${maxCardHeight}px`);
      
      // Make image containers perfectly circular
      chefCards.forEach(card => {
        const imageContainer = card.querySelector('.chef-image-container');
        if (imageContainer) {
          // Make container square with equal width and height
          const containerWidth = imageContainer.offsetWidth;
          const size = Math.max(containerWidth, maxImageHeight);
          
          // Apply circular styling
          imageContainer.style.width = `${size}px`;
          imageContainer.style.height = `${size}px`;
          imageContainer.style.borderRadius = '50%'; // Perfect circle
          imageContainer.style.overflow = 'hidden';
          imageContainer.style.margin = '0 auto 15px auto'; // Center horizontally
          imageContainer.style.boxShadow = '0 4px 10px rgba(0, 0, 0, 0.1)';
          
          // Ensure images maintain aspect ratio and cover the container
          const img = imageContainer.querySelector('img');
          if (img) {
            img.style.objectFit = 'cover';
            img.style.width = '100%';
            img.style.height = '100%';
          }
        }
      });
      
      // Apply responsive sizing based on viewport
      const viewportWidth = window.innerWidth;
      let circleSize;
      
      if (viewportWidth <= 576) {
        circleSize = 150; // Mobile phones
      } else if (viewportWidth <= 768) {
        circleSize = 180; // Small tablets
      } else if (viewportWidth <= 992) {
        circleSize = 200; // Tablets
      } else if (viewportWidth <= 1200) {
        circleSize = 220; // Small desktops
      } else {
        circleSize = 240; // Large desktops
      }
      
      // Apply the responsive circle size
      chefCards.forEach(card => {
        const imageContainer = card.querySelector('.chef-image-container');
        if (imageContainer) {
          imageContainer.style.width = `${circleSize}px`;
          imageContainer.style.height = `${circleSize}px`;
        }
      });
      
      // Second pass: apply consistent heights for info containers
      chefCards.forEach(card => {
        // Set fixed height on the card with additional space for circular image
        card.style.height = `${maxCardHeight + 20}px`;
        
        const infoContainer = card.querySelector('.chef-info-container');
        
        if (infoContainer) {
          infoContainer.style.height = `${maxInfoHeight}px`;
          infoContainer.style.textAlign = 'center'; // Center text
        }
      });
    }
    
    // Move to a specific slide
    function goToSlide(index) {
      // Get current visible cards count
      const visibleCards = getVisibleCardsCount();
      
      // Calculate max index based on visible cards
      const maxIndex = Math.max(0, chefCards.length - visibleCards);
      
      // Ensure index is within bounds
      index = Math.max(0, Math.min(index, maxIndex));
      currentIndex = index;
      
      // Calculate card width including margins
      const card = chefCards[0];
      
      // Force layout recalculation
      void card.offsetWidth;
      
      const cardWidth = card.offsetWidth;
      const cardStyle = window.getComputedStyle(card);
      const cardMargin = parseInt(cardStyle.marginRight) || 0;
      const totalCardWidth = cardWidth + cardMargin;
      
      console.log(`Chef card dimensions: width=${cardWidth}, margin=${cardMargin}, total=${totalCardWidth}`);
      
      // Apply transform
      chefsSlider.style.transition = 'transform 0.5s ease';
      chefsSlider.style.transform = `translateX(-${index * totalCardWidth}px)`;
      console.log(`Chef slider moved to index ${index}, transform: translateX(-${index * totalCardWidth}px)`);
      
      // Update dots
      chefsDots.forEach((dot, i) => {
        dot.classList.toggle('active', i === currentIndex);
        dot.setAttribute('aria-current', i === currentIndex ? 'true' : 'false');
      });
      
      // Update button states
      if (chefsSliderPrev && chefsSliderNext) {
        const isAtStart = currentIndex === 0;
        const isAtEnd = currentIndex === maxIndex;
        
        chefsSliderPrev.disabled = isAtStart;
        chefsSliderNext.disabled = isAtEnd;
        chefsSliderPrev.style.opacity = isAtStart ? '0.5' : '1';
        chefsSliderNext.style.opacity = isAtEnd ? '0.5' : '1';
      }
    }
    
    // Start auto-sliding
    function startAutoSlide() {
      stopAutoSlide();
      
      console.log('Starting chef slider auto slide');
      
      autoSlideInterval = setInterval(() => {
        // Get current visible cards count
        const visibleCards = getVisibleCardsCount();
        
        // Calculate max index based on visible cards
        const maxIndex = Math.max(0, chefCards.length - visibleCards);
        
        // Move to the next slide, with looping back to start when reaching the end
        let nextIndex = currentIndex + 1;
        if (nextIndex > maxIndex) {
          nextIndex = 0;
        }
        
        console.log(`Chef slider moving from ${currentIndex} to ${nextIndex} (max: ${maxIndex})`);
        goToSlide(nextIndex);
      }, 3000); // 3 seconds interval to match other sliders
    }
    
    // Stop auto-sliding
    function stopAutoSlide() {
      if (autoSlideInterval) {
        clearInterval(autoSlideInterval);
        autoSlideInterval = null;
      }
    }
    
    // Initialize slider
    function initializeSlider() {
      normalizeCardDimensions();
      goToSlide(0);
      startAutoSlide();
    }
    
    // Handle window resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(() => {
        console.log('Window resized, updating chef slider');
        normalizeCardDimensions();
        goToSlide(currentIndex);
      }, 200);
    });
    
    // Previous button click handler
    if (chefsSliderPrev) {
      chefsSliderPrev.addEventListener('click', function() {
        stopAutoSlide();
        goToSlide(currentIndex - 1);
      });
    }
    
    // Next button click handler
    if (chefsSliderNext) {
      chefsSliderNext.addEventListener('click', function() {
        stopAutoSlide();
        goToSlide(currentIndex + 1);
      });
    }
    
    // Dot navigation
    chefsDots.forEach((dot, i) => {
      dot.addEventListener('click', function() {
        stopAutoSlide();
        goToSlide(i);
      });
    });
    
    // Follow button functionality
    followButtons.forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        const isFollowing = this.classList.contains('following');
        this.classList.toggle('following', !isFollowing);
        this.innerHTML = isFollowing ? 
          '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg> Follow' : 
          '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg> Following';
      });
    });
    
    // Initialize the slider
    initializeSlider();
  })();
  
  /**
   * Stats Counter Animation - Optimized with IntersectionObserver
   */
  const statsSection = document.querySelector('.stats-section');
  const statNumbers = document.querySelectorAll('.stat-number');
  let countersStarted = false;
  
  /**
   * Start the counter animation for stats
   */
  function startCounters() {
    if (!statsSection || countersStarted) return;
    
    countersStarted = true;
    
    statNumbers.forEach(counter => {
      // Extract the target number, handling both numbers with and without '+' sign
      const hasPlus = counter.textContent.includes('+');
      const target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
      let count = 0;
      
      // Animation settings
      const duration = 2000; // 2 seconds
      const fps = 60;
      const frameDuration = 1000 / fps;
      const totalFrames = Math.round(duration / frameDuration);
      const increment = target / totalFrames;
      
      // Start from 0
      counter.textContent = '0' + (hasPlus ? '+' : '');
      
      // Use requestAnimationFrame for smoother animation
      function updateCount(currentFrame) {
        if (currentFrame >= totalFrames) {
          counter.textContent = target + (hasPlus ? '+' : '');
          return;
        }
        
        count += increment;
        counter.textContent = Math.round(count) + (hasPlus ? '+' : '');
        
        requestAnimationFrame(() => updateCount(currentFrame + 1));
      }
      
      requestAnimationFrame(() => updateCount(0));
    });
  }
  
  /**
   * Use IntersectionObserver for better performance than scroll events
   */
  if (statsSection && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          startCounters();
          observer.unobserve(entry.target); // Stop observing once animation starts
        }
      });
    }, { threshold: 0.1 }); // Start when 10% of the element is visible
    
    observer.observe(statsSection);
  } else {
    // Fallback for browsers that don't support IntersectionObserver
    function isInViewport(element) {
      if (!element) return false;
      const rect = element.getBoundingClientRect();
      return (
        rect.top <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.bottom >= 0
      );
    }
    
    // Check on scroll with throttling
    let scrollTimeout;
    window.addEventListener('scroll', () => {
      if (scrollTimeout) return;
      scrollTimeout = setTimeout(() => {
        if (isInViewport(statsSection)) {
          startCounters();
        }
        scrollTimeout = null;
      }, 100);
    });
    
    // Check on page load
    if (isInViewport(statsSection)) {
      startCounters();
    }
  }
  
  /**
   * Live Cooking Session tabs with accessibility improvements
   */
  // Use an immediately invoked function expression (IIFE) to run the code immediately
  (function() {
    // Tab switching functionality
    const liveTabs = document.querySelectorAll('.live-tab');
    const liveSessionsSlider = document.getElementById('live-sessions-slider');
    const upcomingSessionsSlider = document.getElementById('upcoming-sessions-slider');
    
    // Navigation elements
    const prevButton = document.querySelector('.live-slider-prev');
    const nextButton = document.querySelector('.live-slider-next');
    const dots = document.querySelectorAll('.live-slider-dots .live-dot');
    
    // Track current slider states
    let liveCurrentSlide = 0;
    let upcomingCurrentSlide = 0;
    let autoSlideInterval = null;
    
    // Simple function to switch tabs
    function switchTab(tabName) {
      // Update tab states
      liveTabs.forEach(tab => {
        const isActive = tab.getAttribute('data-tab') === tabName;
        tab.classList.toggle('active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
      
      // Update content visibility
      if (tabName === 'live' && liveSessionsSlider && upcomingSessionsSlider) {
        liveSessionsSlider.style.display = 'flex';
        liveSessionsSlider.setAttribute('aria-hidden', 'false');
        upcomingSessionsSlider.style.display = 'none';
        upcomingSessionsSlider.setAttribute('aria-hidden', 'true');
        updateDots(liveCurrentSlide);
        startAutoSlide();
      } else if (tabName === 'upcoming' && liveSessionsSlider && upcomingSessionsSlider) {
        liveSessionsSlider.style.display = 'none';
        liveSessionsSlider.setAttribute('aria-hidden', 'true');
        upcomingSessionsSlider.style.display = 'flex';
        upcomingSessionsSlider.setAttribute('aria-hidden', 'false');
        updateDots(upcomingCurrentSlide);
        startAutoSlide();
      }
    }
    
    // Get the currently active slider
    function getActiveSlider() {
      const activeTab = document.querySelector('.live-tab.active');
      if (!activeTab) return null;
      
      const tabName = activeTab.getAttribute('data-tab');
      return tabName === 'live' ? liveSessionsSlider : upcomingSessionsSlider;
    }
    
    // Get the number of visible cards based on viewport width
    function getVisibleCardsCount() {
      const viewportWidth = window.innerWidth;
      if (viewportWidth <= 576) {
        return 1; // Mobile phones
      } else if (viewportWidth <= 768) {
        return 1; // Small tablets
      } else if (viewportWidth <= 992) {
        return 2; // Tablets
      } else if (viewportWidth <= 1200) {
        return 3; // Small desktops
      } else {
        return 4; // Large desktops
      }
    }
    
    // Get current slide index for active tab
    function getCurrentSlide() {
      const activeTab = document.querySelector('.live-tab.active');
      if (!activeTab) return 0;
      
      const tabName = activeTab.getAttribute('data-tab');
      return tabName === 'live' ? liveCurrentSlide : upcomingCurrentSlide;
    }
    
    // Set current slide index for active tab
    function setCurrentSlide(index) {
      const activeTab = document.querySelector('.live-tab.active');
      if (!activeTab) return;
      
      const tabName = activeTab.getAttribute('data-tab');
      if (tabName === 'live') {
        liveCurrentSlide = index;
      } else {
        upcomingCurrentSlide = index;
      }
    }
    
    // Update the dots indicator
    function updateDots(index) {
      dots.forEach((dot, i) => {
        dot.classList.toggle('active', i === index);
      });
    }
    
    // Move to a specific slide
    function goToSlide(index) {
      const slider = getActiveSlider();
      if (!slider) {
        console.log('No slider found in goToSlide');
        return;
      }
      
      const cards = slider.querySelectorAll('.live-card');
      if (!cards.length) {
        console.log('No cards found in goToSlide');
        return;
      }
      
      // Get the number of visible cards based on viewport
      const visibleCards = getVisibleCardsCount();
      console.log(`Visible cards for current viewport: ${visibleCards}`);
      
      // Calculate max index based on visible cards
      const maxIndex = Math.max(0, cards.length - visibleCards);
      
      // Ensure index is within bounds
      index = Math.max(0, Math.min(index, maxIndex));
      
      // Update current slide tracker
      setCurrentSlide(index);
      
      // Calculate card width including margins
      const card = cards[0];
      
      // Force layout recalculation
      void card.offsetWidth;
      
      const cardWidth = card.offsetWidth;
      const cardStyle = window.getComputedStyle(card);
      const cardMargin = parseInt(cardStyle.marginRight) || 0;
      const totalCardWidth = cardWidth + cardMargin;
      
      console.log(`Card dimensions: width=${cardWidth}, margin=${cardMargin}, total=${totalCardWidth}`);
      
      // Apply transform
      slider.style.transition = 'transform 0.5s ease';
      slider.style.transform = `translateX(-${index * totalCardWidth}px)`;
      console.log(`Applied transform: translateX(-${index * totalCardWidth}px)`);
      
      // Update dots
      updateDots(index);
      
      // Update button states
      if (prevButton && nextButton) {
        prevButton.disabled = index === 0;
        nextButton.disabled = index === maxIndex;
        prevButton.style.opacity = index === 0 ? '0.5' : '1';
        nextButton.style.opacity = index === maxIndex ? '0.5' : '1';
      }
    }
    
    // Start auto-sliding
    function startAutoSlide() {
      stopAutoSlide();
      
      console.log('Starting auto slide for', getActiveSlider() ? getActiveSlider().id : 'no slider');
      
      autoSlideInterval = setInterval(() => {
        const slider = getActiveSlider();
        if (!slider) {
          console.log('No active slider found');
          return;
        }
        
        const cards = slider.querySelectorAll('.live-card');
        if (!cards.length) {
          console.log('No cards found in slider');
          return;
        }
        
        // Get the number of visible cards based on viewport
        const visibleCards = getVisibleCardsCount();
        
        // Calculate max index based on visible cards
        const maxIndex = Math.max(0, cards.length - visibleCards);
        
        const currentIndex = getCurrentSlide();
        let nextIndex = currentIndex + 1;
        
        // Loop back to the beginning if we reach the end
        if (nextIndex > maxIndex) {
          nextIndex = 0;
        }
        
        console.log(`Moving from slide ${currentIndex} to ${nextIndex} (max: ${maxIndex})`);
        goToSlide(nextIndex);
      }, 3000); // 3 seconds interval
    }
    
    // Stop auto-sliding
    function stopAutoSlide() {
      if (autoSlideInterval) {
        clearInterval(autoSlideInterval);
        autoSlideInterval = null;
      }
    }
    
    // Initialize tab click handlers
    liveTabs.forEach(tab => {
      tab.addEventListener('click', function() {
        const tabName = this.getAttribute('data-tab');
        switchTab(tabName);
      });
    });
    
    // Previous button click handler
    if (prevButton) {
      prevButton.addEventListener('click', function() {
        stopAutoSlide();
        goToSlide(getCurrentSlide() - 1);
      });
    }
    
    // Next button click handler
    if (nextButton) {
      nextButton.addEventListener('click', function() {
        stopAutoSlide();
        goToSlide(getCurrentSlide() + 1);
      });
    }
    
    // Dot navigation
    dots.forEach((dot, i) => {
      dot.addEventListener('click', function() {
        stopAutoSlide();
        goToSlide(i);
      });
    });
    
    // Initialize sliders with proper styles
    if (liveSessionsSlider) {
      liveSessionsSlider.style.display = 'flex';
      liveSessionsSlider.style.transition = 'transform 0.5s ease';
      liveSessionsSlider.style.width = '100%';
      liveSessionsSlider.style.overflow = 'visible';
    }
    
    if (upcomingSessionsSlider) {
      upcomingSessionsSlider.style.transition = 'transform 0.5s ease';
      upcomingSessionsSlider.style.width = '100%';
      upcomingSessionsSlider.style.overflow = 'visible';
    }
    
    // Force layout recalculation
    if (liveSessionsSlider) void liveSessionsSlider.offsetWidth;
    if (upcomingSessionsSlider) void upcomingSessionsSlider.offsetWidth;
    
    // Start with the active tab
    const activeTab = document.querySelector('.live-tab.active');
    if (activeTab) {
      const tabName = activeTab.getAttribute('data-tab');
      switchTab(tabName);
    }
    
    // Handle window resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(() => {
        // Get current visible cards count after resize
        const visibleCards = getVisibleCardsCount();
        console.log(`Window resized. Visible cards: ${visibleCards}`);
        
        // Recalculate position based on new viewport size
        const currentIndex = getCurrentSlide();
        goToSlide(currentIndex);
      }, 200);
    });
  })();
  
  /**
   * Recipe of the Week Slider Functionality
   */
  const recipeSlider = document.querySelector('.recipe-slider');
  const recipeCards = document.querySelectorAll('.recipe-card');
  const recipeDots = document.querySelectorAll('.slider-dots .dot');
  const recipePrevButton = document.querySelector('.slider-prev');
  const recipeNextButton = document.querySelector('.slider-next');
  
  let recipeCurrentIndex = 0;
  let recipeCardWidth = 0;
  let recipeVisibleCards = 1; // Default for mobile
  let recipeAutoSlideInterval;
  
  // Debug log function
  function logDebug(message) {
    console.log(`[Feedora Debug] ${message}`);
  }
  
  function updateRecipeVisibleCards() {
    // Determine visible cards based on screen width
    if (window.innerWidth <= 768) {
      recipeVisibleCards = 1;
    } else if (window.innerWidth <= 1024) {
      recipeVisibleCards = 2;
    } else {
      recipeVisibleCards = 3;
    }
    
    // Update card width
    if (recipeCards.length > 0) {
      const card = recipeCards[0];
      const cardStyle = window.getComputedStyle(card);
      recipeCardWidth = card.offsetWidth + 
                        parseInt(cardStyle.marginRight, 10) + 
                        parseInt(cardStyle.marginLeft, 10);
      
      logDebug(`Recipe card width calculated: ${recipeCardWidth}px`);
    }
    
    // Reset position
    goToRecipeSlide(recipeCurrentIndex);
    
    // Always start auto-slide regardless of viewport
    startRecipeAutoSlide();
  }
  
  function startRecipeAutoSlide() {
    // Clear any existing interval first
    stopRecipeAutoSlide();
    
    // Only start auto-sliding if there are more cards than visible slots
    if (recipeCards.length > recipeVisibleCards) {
      logDebug(`Starting recipe auto-slide with ${recipeCards.length} cards`);
      recipeAutoSlideInterval = setInterval(function() {
        // Move to the next slide, with looping back to start when reaching the end
        const nextIndex = (recipeCurrentIndex + 1) % (recipeCards.length - recipeVisibleCards + 1);
        goToRecipeSlide(nextIndex);
        logDebug(`Recipe slider moved to index ${nextIndex}`);
      }, 3000); // 3 seconds interval as requested
    } else {
      logDebug('Not enough recipe cards to auto-slide');
    }
  }
  
  function stopRecipeAutoSlide() {
    if (recipeAutoSlideInterval) {
      clearInterval(recipeAutoSlideInterval);
      recipeAutoSlideInterval = null;
    }
  }
  
  function goToRecipeSlide(index) {
    if (!recipeSlider) {
      logDebug('Recipe slider element not found');
      return;
    }
    
    // Calculate max index
    const maxIndex = Math.max(0, recipeCards.length - recipeVisibleCards);
    
    // Clamp index within valid range
    index = Math.max(0, Math.min(index, maxIndex));
    recipeCurrentIndex = index;
    
    // Calculate the translation distance precisely
    const translationDistance = recipeCurrentIndex * recipeCardWidth;
    
    // Apply transform directly and ensure it's applied
    recipeSlider.style.transition = 'transform 0.5s ease';
    recipeSlider.style.transform = `translateX(-${translationDistance}px)`;
    
    logDebug(`Recipe slide to index ${index}, transform: translateX(-${translationDistance}px)`);
    
    // Update dots
    recipeDots.forEach((dot, i) => {
      dot.classList.toggle('active', i === recipeCurrentIndex);
    });
    
    // Update button states
    if (recipePrevButton && recipeNextButton) {
      const isAtStart = recipeCurrentIndex === 0;
      const isAtEnd = recipeCurrentIndex === maxIndex;
      
      recipePrevButton.disabled = isAtStart;
      recipeNextButton.disabled = isAtEnd;
      recipePrevButton.style.opacity = isAtStart ? '0.5' : '1';
      recipeNextButton.style.opacity = isAtEnd ? '0.5' : '1';
    }
  }
  
  // Initialize recipe slider if elements exist
  if (recipeSlider && recipeCards.length > 0) {
    logDebug(`Initializing recipe slider with ${recipeCards.length} cards`);
    
    // Ensure slider container has proper styling
    recipeSlider.style.display = 'flex';
    recipeSlider.style.width = '100%';
    recipeSlider.style.transition = 'transform 0.5s ease';
    
    // Initialize on page load
    updateRecipeVisibleCards();
    
    // Handle window resize with debounce
    window.addEventListener('resize', debounce(() => {
      logDebug('Window resized, updating recipe slider');
      updateRecipeVisibleCards();
    }, 150));
    
    // Previous slide button for recipe slider
    if (recipePrevButton) {
      recipePrevButton.addEventListener('click', function() {
        stopRecipeAutoSlide(); // Stop auto-slide when user interacts
        goToRecipeSlide(recipeCurrentIndex - 1);
      });
    }
    
    // Next slide button for recipe slider
    if (recipeNextButton) {
      recipeNextButton.addEventListener('click', function() {
        stopRecipeAutoSlide(); // Stop auto-slide when user interacts
        goToRecipeSlide(recipeCurrentIndex + 1);
      });
    }
    
    // Dot navigation for recipe slider
    recipeDots.forEach((dot, i) => {
      dot.addEventListener('click', function() {
        stopRecipeAutoSlide(); // Stop auto-slide when user interacts
        goToRecipeSlide(i);
      });
    });
  }
  
  // Community Feed Slider Functionality - Simplified Implementation
  document.addEventListener('DOMContentLoaded', function() {
    const communitySlider = document.querySelector('#community-slider');
    const communityPrevButton = document.querySelector('.community-slider-prev');
    const communityNextButton = document.querySelector('.community-slider-next');
    const communityDots = document.querySelectorAll('.community-dot');
    const postCards = document.querySelectorAll('.post-card');
    
    // Initialize slider state
    let communityCurrentIndex = 0;
    let communityCardWidth = 0;
    let communityVisibleCards = 3; // Default for desktop
    let communityAutoSlideInterval;
    
    // Only proceed if we have the necessary elements
    if (!communitySlider || !postCards.length) {
      console.log('Community slider not found or no post cards');
      return;
    }
    
    console.log('Initializing community slider');
    
    // Set proper styling for the slider container
    communitySlider.style.display = 'flex';
    communitySlider.style.width = '100%';
    communitySlider.style.transition = 'transform 0.5s ease';
    
    // Calculate visible cards and card width
    function updateVisibleCards() {
      // Determine visible cards based on screen width
      if (window.innerWidth <= 768) {
        communityVisibleCards = 1; // Mobile phones and small tablets
      } else if (window.innerWidth <= 1200) {
        communityVisibleCards = 2; // Tablets and small desktops
      } else {
        communityVisibleCards = 3; // Large desktops
      }
      
      console.log(`Visible cards: ${communityVisibleCards} at width ${window.innerWidth}px`);
      
      // Calculate card width including margins
      if (postCards.length > 0) {
        const card = postCards[0];
        const cardStyle = window.getComputedStyle(card);
        const marginLeft = parseInt(cardStyle.marginLeft, 10) || 0;
        const marginRight = parseInt(cardStyle.marginRight, 10) || 0;
        communityCardWidth = card.offsetWidth + marginLeft + marginRight;
        console.log(`Card width: ${communityCardWidth}px (${card.offsetWidth}px + ${marginLeft}px + ${marginRight}px margins)`);
      }
      
      // Update slider position after resize
      goToSlide(communityCurrentIndex);
    }
    
    // Go to a specific slide
    function goToSlide(index) {
      // Calculate max index (maximum possible slide position)
      const maxIndex = Math.max(0, postCards.length - communityVisibleCards);
      
      // Clamp index within valid range
      index = Math.max(0, Math.min(index, maxIndex));
      communityCurrentIndex = index;
      
      console.log(`Moving to slide ${index} of ${maxIndex} max`);
      
      // Calculate the translation distance
      const translationDistance = communityCurrentIndex * communityCardWidth;
      
      // Apply transform with transition for smooth animation
      communitySlider.style.transform = `translateX(-${translationDistance}px)`;
      
      // Update dots to show current position
      communityDots.forEach((dot, i) => {
        if (i <= maxIndex) {
          dot.classList.toggle('active', i === communityCurrentIndex);
        }
      });
      
      // Update button states
      if (communityPrevButton && communityNextButton) {
        const isAtStart = communityCurrentIndex === 0;
        const isAtEnd = communityCurrentIndex === maxIndex;
        
        communityPrevButton.disabled = isAtStart;
        communityNextButton.disabled = isAtEnd;
        communityPrevButton.style.opacity = isAtStart ? '0.5' : '1';
        communityNextButton.style.opacity = isAtEnd ? '0.5' : '1';
      }
    }
    
    // Auto-slide functionality
    function startAutoSlide() {
      stopAutoSlide();
      
      if (postCards.length > communityVisibleCards) {
        communityAutoSlideInterval = setInterval(function() {
          const maxIndex = Math.max(0, postCards.length - communityVisibleCards);
          let nextIndex = communityCurrentIndex + 1;
          
          // Loop back to start when reaching the end
          if (nextIndex > maxIndex) {
            nextIndex = 0;
          }
          
          goToSlide(nextIndex);
        }, 4000); // 4 seconds interval
      }
    }
    
    // Stop auto-sliding
    function stopAutoSlide() {
      if (communityAutoSlideInterval) {
        clearInterval(communityAutoSlideInterval);
        communityAutoSlideInterval = null;
      }
    }
    
    // Initialize the slider
    updateVisibleCards();
    
    // Set up event listeners
    if (communityPrevButton) {
      communityPrevButton.addEventListener('click', function() {
        stopAutoSlide();
        goToSlide(communityCurrentIndex - 1);
        setTimeout(startAutoSlide, 5000); // Restart auto-slide after 5 seconds
      });
    }
    
    if (communityNextButton) {
      communityNextButton.addEventListener('click', function() {
        stopAutoSlide();
        goToSlide(communityCurrentIndex + 1);
        setTimeout(startAutoSlide, 5000); // Restart auto-slide after 5 seconds
      });
    }
    
    // Dot navigation
    communityDots.forEach((dot, i) => {
      dot.addEventListener('click', function() {
        stopAutoSlide();
        goToSlide(i);
        setTimeout(startAutoSlide, 5000); // Restart auto-slide after 5 seconds
      });
    });
    
    // Add touch support for mobile devices
    let touchStartX = 0;
    let touchEndX = 0;
    
    communitySlider.addEventListener('touchstart', function(e) {
      touchStartX = e.changedTouches[0].screenX;
    }, {passive: true});
    
    communitySlider.addEventListener('touchend', function(e) {
      touchEndX = e.changedTouches[0].screenX;
      const swipeDistance = touchEndX - touchStartX;
      
      // If swipe distance is significant
      if (Math.abs(swipeDistance) >= 50) {
        stopAutoSlide();
        
        if (swipeDistance > 0) {
          // Swipe right - go to previous slide
          goToSlide(communityCurrentIndex - 1);
        } else {
          // Swipe left - go to next slide
          goToSlide(communityCurrentIndex + 1);
        }
        
        // Restart auto-sliding after 5 seconds
        setTimeout(startAutoSlide, 5000);
      }
    }, {passive: true});
    
    // Handle window resize
    window.addEventListener('resize', function() {
      clearTimeout(window.resizeTimer);
      window.resizeTimer = setTimeout(function() {
        console.log('Window resized, updating community slider');
        updateVisibleCards();
      }, 250);
    });
    
    // Start auto-sliding
    startAutoSlide();
  });
  
  // Community Feed Slider Implementation
  (function initializeCommunitySlider() {
    const communitySlider = document.querySelector('#community-slider');
    const communityPrevButton = document.querySelector('.community-slider-prev');
    const communityNextButton = document.querySelector('.community-slider-next');
    const communityDots = document.querySelectorAll('.community-dot');
    const postCards = document.querySelectorAll('.post-card');
    
    // Check if slider exists
    if (!communitySlider || !postCards.length) {
      console.log('Community slider not found or no post cards');
      return;
    }
    
    console.log(`Found community slider with ${postCards.length} cards`);
    
    // Initialize slider state
    let communityCurrentIndex = 0;
    let communityAutoSlideInterval = null;
    let communityVisibleCards = 1;
    let communityCardWidth = 0;
    
    // Apply initial styling to slider
    communitySlider.style.display = 'flex';
    communitySlider.style.width = '100%';
    communitySlider.style.transition = 'transform 0.5s ease';
    
    // Calculate visible cards and card width
    function updateVisibleCards() {
      // Determine visible cards based on screen width
      if (window.innerWidth <= 768) {
        communityVisibleCards = 1; // Mobile phones and small tablets
      } else if (window.innerWidth <= 1200) {
        communityVisibleCards = 2; // Tablets and small desktops
      } else {
        communityVisibleCards = 3; // Large desktops
      }
      
      console.log(`Visible cards: ${communityVisibleCards} at width ${window.innerWidth}px`);
      
      // Calculate card width including margins
      if (postCards.length > 0) {
        const card = postCards[0];
        const cardStyle = window.getComputedStyle(card);
        const marginLeft = parseInt(cardStyle.marginLeft, 10) || 0;
        const marginRight = parseInt(cardStyle.marginRight, 10) || 0;
        communityCardWidth = card.offsetWidth + marginLeft + marginRight;
        console.log(`Card width: ${communityCardWidth}px (${card.offsetWidth}px + ${marginLeft}px + ${marginRight}px margins)`);
      }
      
      // Update slider position after resize
      goToSlide(communityCurrentIndex);
    }
    
    // Go to a specific slide
    function goToSlide(index) {
      // Calculate max index (maximum possible slide position)
      const maxIndex = Math.max(0, postCards.length - communityVisibleCards);
      
      // Clamp index within valid range
      index = Math.max(0, Math.min(index, maxIndex));
      communityCurrentIndex = index;
      
      console.log(`Moving to slide ${index} of ${maxIndex} max`);
      
      // Calculate the translation distance
      const translationDistance = communityCurrentIndex * communityCardWidth;
      
      // Apply transform with transition for smooth animation
      communitySlider.style.transform = `translateX(-${translationDistance}px)`;
      
      // Update dots to show current position
      communityDots.forEach((dot, i) => {
        if (i <= maxIndex) {
          dot.classList.toggle('active', i === communityCurrentIndex);
        }
      });
    }
    
    // Start auto-sliding
    function startAutoSlide() {
      if (communityAutoSlideInterval) {
        clearInterval(communityAutoSlideInterval);
      }
      
      communityAutoSlideInterval = setInterval(() => {
        const maxIndex = Math.max(0, postCards.length - communityVisibleCards);
        let nextIndex = communityCurrentIndex + 1;
        
        // Loop back to start when reaching the end
        if (nextIndex > maxIndex) {
          nextIndex = 0;
        }
        
        goToSlide(nextIndex);
      }, 4000); // 4 seconds interval
    }
    
    // Stop auto-sliding
    function stopAutoSlide() {
      if (communityAutoSlideInterval) {
        clearInterval(communityAutoSlideInterval);
        communityAutoSlideInterval = null;
      }
    }
    
    // Initialize the slider
    updateVisibleCards();
    
    // Set up event listeners
    if (communityPrevButton) {
      communityPrevButton.addEventListener('click', function() {
        stopAutoSlide();
        goToSlide(communityCurrentIndex - 1);
        setTimeout(startAutoSlide, 5000); // Restart auto-slide after 5 seconds
      });
    }
    
    if (communityNextButton) {
      communityNextButton.addEventListener('click', function() {
        stopAutoSlide();
        goToSlide(communityCurrentIndex + 1);
        setTimeout(startAutoSlide, 5000); // Restart auto-slide after 5 seconds
      });
    }
    
    // Dot navigation
    communityDots.forEach((dot, i) => {
      dot.addEventListener('click', function() {
        stopAutoSlide();
        goToSlide(i);
        setTimeout(startAutoSlide, 5000); // Restart auto-slide after 5 seconds
      });
    });
    
    // Add touch support for mobile devices
    let touchStartX = 0;
    let touchEndX = 0;
    
    communitySlider.addEventListener('touchstart', function(e) {
      touchStartX = e.changedTouches[0].screenX;
    }, {passive: true});
    
    communitySlider.addEventListener('touchend', function(e) {
      touchEndX = e.changedTouches[0].screenX;
      const swipeDistance = touchEndX - touchStartX;
      
      // If swipe distance is significant
      if (Math.abs(swipeDistance) >= 50) {
        stopAutoSlide();
        
        if (swipeDistance > 0) {
          // Swipe right - go to previous slide
          goToSlide(communityCurrentIndex - 1);
        } else {
          // Swipe left - go to next slide
          goToSlide(communityCurrentIndex + 1);
        }
        
        // Restart auto-sliding after 5 seconds
        setTimeout(startAutoSlide, 5000);
      }
    }, {passive: true});
    
    // Handle window resize
    window.addEventListener('resize', function() {
      clearTimeout(window.resizeTimer);
      window.resizeTimer = setTimeout(function() {
        console.log('Window resized, updating community slider');
        updateVisibleCards();
      }, 250);
    });
    
    // Start auto-sliding
    startAutoSlide();
  })();
  
  // Add a debounce function for resize events if it doesn't exist
  function debounce(func, wait) {
    let timeout;
    return function() {
      const context = this;
      const args = arguments;
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(context, args), wait);
    };
  }
  
  // Touch support for community slider is now handled in the new implementation above
  
  // Initialize reaction functionality
  initializeReactions();
  
  function initializeReactions() {
    const reactionIcons = document.querySelectorAll('.reaction-icon');
    const likeButtons = document.querySelectorAll('.like-button');
    const likeButtonContainers = document.querySelectorAll('.like-button-container');
    const globalReactionContainer = document.getElementById('global-reaction-container');
    const reactionsPanel = document.querySelector('#global-reaction-container .reaction-icons');
    
    let currentPostId = null;
    
    // Position reaction icons on hover
    likeButtonContainers.forEach(container => {
      container.addEventListener('mouseenter', function(e) {
        const buttonRect = this.getBoundingClientRect();
        const postCard = this.closest('.post-card');
        currentPostId = postCard.id;
        
        // Position the reactions panel ABOVE the like button
        reactionsPanel.style.top = (buttonRect.top - 60) + 'px'; // Position above with space for the panel
        reactionsPanel.style.left = (buttonRect.left + buttonRect.width/2) + 'px';
        reactionsPanel.style.transform = 'translateX(-50%)';
        
        // Show the reactions panel
        reactionsPanel.style.opacity = '1';
        reactionsPanel.style.visibility = 'visible';
      });
      
      container.addEventListener('mouseleave', function(e) {
        // Check if mouse is over the reactions panel
        const relatedTarget = e.relatedTarget;
        if (!globalReactionContainer.contains(relatedTarget)) {
          // Hide the reactions panel
          reactionsPanel.style.opacity = '0';
          reactionsPanel.style.visibility = 'hidden';
        }
      });
    });
    
    // Hide reactions panel when mouse leaves it
    globalReactionContainer.addEventListener('mouseleave', function() {
      reactionsPanel.style.opacity = '0';
      reactionsPanel.style.visibility = 'hidden';
    });
    
    // Handle reaction icon clicks
    reactionIcons.forEach(icon => {
      icon.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const reaction = this.getAttribute('data-reaction');
        
        // Find the like button container for the current post
        const postCard = document.getElementById(currentPostId);
        const container = postCard.querySelector('.like-button-container');
        const likeButton = container.querySelector('.like-button');
        const likesCountElement = container.parentElement.querySelector('.likes-count');
        
        // Remove any existing reaction classes
        likeButton.classList.remove('reacted-yummy', 'reacted-delicious', 'reacted-spicy', 'reacted-sweet', 'reacted-recipe');
        
        // Add the new reaction class
        likeButton.classList.add(`reacted-${reaction}`);
        
        // Update the SVG with a colored version based on reaction
        updateLikeButtonIcon(likeButton, reaction);
        
        // Update the likes count text to show the reaction
        updateLikesCount(likesCountElement, reaction);
        
        // Hide the reaction panel after selection
        const reactionPanel = this.closest('.reaction-icons');
        reactionPanel.style.opacity = '0';
        reactionPanel.style.visibility = 'hidden';
        
        // Show it again on next hover
        setTimeout(() => {
          reactionPanel.removeAttribute('style');
        }, 500);
      });
    });
    
    // Handle like button clicks (default like reaction)
    likeButtons.forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const container = this.closest('.like-button-container');
        const likesCountElement = container.parentElement.querySelector('.likes-count');
        
        // Toggle like state
        if (this.classList.contains('reacted-like')) {
          // Unlike
          this.classList.remove('reacted-like');
          updateLikeButtonIcon(this, 'none');
          likesCountElement.textContent = '93.2k likes'; // Reset to original count
        } else {
          // Like
          // Remove any existing reaction classes first
          this.classList.remove('reacted-yummy', 'reacted-delicious', 'reacted-spicy', 'reacted-sweet', 'reacted-recipe');
          this.classList.add('reacted-yummy');
          updateLikeButtonIcon(this, 'yummy');
          updateLikesCount(likesCountElement, 'yummy');
        }
      });
    });
  }
  
  function updateLikeButtonIcon(button, reaction) {
    // Replace the SVG with colored version based on reaction
    const svg = button.querySelector('svg');
    
    if (reaction === 'none') {
      // Reset to original
      svg.style.fill = 'none';
      svg.style.stroke = 'currentColor';
      return;
    }
    
    // Set colors based on reaction type
    switch(reaction) {
      case 'yummy':
        svg.style.fill = '#FF5722';
        svg.style.stroke = '#FF5722';
        break;
      case 'delicious':
        svg.style.fill = '#F25268';
        svg.style.stroke = '#F25268';
        break;
      case 'spicy':
        svg.style.fill = '#E91E63';
        svg.style.stroke = '#E91E63';
        break;
      case 'sweet':
        svg.style.fill = '#9C27B0';
        svg.style.stroke = '#9C27B0';
        break;
      case 'recipe':
        svg.style.fill = '#00B489';
        svg.style.stroke = '#00B489';
        break;
    }
  }
  
  function updateLikesCount(element, reaction) {
    // Update text to show the reaction type
    if (!element) return;
    
    const reactionText = {
      'yummy': 'found yummy',
      'delicious': 'found delicious',
      'spicy': 'found spicy',
      'sweet': 'found sweet',
      'recipe': 'saved recipe'
    };
    
    element.textContent = `You ${reactionText[reaction]} this with 93.2k others`;
  }
  
  /**
   * Testimonials Slider Functionality
   * Auto-slides every 3 seconds and allows manual navigation
   * Shows two cards side by side in desktop view
   */
  (function initializeTestimonialsSlider() {
    const testimonialsSlider = document.querySelector('.testimonials-slider');
    const testimonialDots = document.querySelectorAll('.testimonial-dot');
    const testimonialCards = document.querySelectorAll('.testimonial-card');
    
    // Check if slider exists
    if (!testimonialsSlider || !testimonialCards.length) {
      console.log('Testimonials slider not found or no testimonial cards');
      return;
    }
    
    console.log(`Found testimonials slider with ${testimonialCards.length} cards`);
    
    // Initialize slider state
    let currentIndex = 0;
    let autoSlideInterval = null;
    let slideWidth = 0;
    let visibleCards = 2; // Default to 2 cards visible in desktop
    
    // Calculate how many cards are visible based on viewport width
    function calculateVisibleCards() {
      const viewportWidth = window.innerWidth;
      if (viewportWidth < 768) {
        visibleCards = 1; // Mobile view - 1 card visible
      } else {
        visibleCards = 2; // Desktop view - 2 cards visible
      }
      return visibleCards;
    }
    
    // Calculate slide width based on visible cards
    function calculateSlideWidth() {
      calculateVisibleCards();
      // For desktop (2 cards visible), each slide moves by 50%
      // For mobile (1 card visible), each slide moves by 100%
      slideWidth = visibleCards === 1 ? 100 : 50;
      return slideWidth;
    }
    
    // Function to move to a specific slide
    function goToSlide(index) {
      // Calculate max index based on visible cards
      const maxIndex = testimonialCards.length - visibleCards;
      
      // Ensure index is within bounds
      if (index < 0) {
        // Loop back to end
        index = maxIndex;
      } else if (index > maxIndex) {
        // Loop back to beginning
        index = 0;
      }
      
      currentIndex = index;
      
      // Calculate slide width for current viewport
      calculateSlideWidth();
      
      // Update slider position - move by percentage based on current index
      testimonialsSlider.style.transform = `translateX(-${currentIndex * slideWidth}%)`;
      
      // Update dots - only show active dot for current visible card group
      testimonialDots.forEach((dot, i) => {
        // For desktop with 2 cards visible, dots represent pairs of cards
        // For mobile with 1 card visible, dots represent individual cards
        const dotIndex = visibleCards === 1 ? i : Math.floor(i / 2);
        const currentDotIndex = visibleCards === 1 ? currentIndex : Math.floor(currentIndex / 2);
        dot.classList.toggle('active', dotIndex === currentDotIndex);
      });
    }
    
    // Start auto-sliding
    function startAutoSlide() {
      if (autoSlideInterval) {
        clearInterval(autoSlideInterval);
      }
      
      autoSlideInterval = setInterval(() => {
        // Move to next card or pair of cards
        goToSlide(currentIndex + 1);
      }, 3000); // Change slide every 3 seconds
    }
    
    // Stop auto-sliding
    function stopAutoSlide() {
      if (autoSlideInterval) {
        clearInterval(autoSlideInterval);
        autoSlideInterval = null;
      }
    }
    
    // Initialize slider
    function initializeSlider() {
      // Set initial card widths based on viewport
      calculateVisibleCards();
      
      // Apply initial styles to cards
      testimonialCards.forEach(card => {
        if (visibleCards === 2) {
          // Desktop view - 2 cards visible
          card.style.width = '48%';
          card.style.marginRight = '2%';
        } else {
          // Mobile view - 1 card visible
          card.style.width = '100%';
          card.style.marginRight = '0';
        }
      });
      
      // Set initial position
      goToSlide(0);
      
      // Start auto-sliding
      startAutoSlide();
    }
    
    // Add click event to dots for manual navigation
    testimonialDots.forEach((dot, i) => {
      dot.addEventListener('click', function() {
        stopAutoSlide();
        // For desktop with 2 cards visible, each dot represents 2 cards
        const targetIndex = visibleCards === 1 ? i : i * 2;
        goToSlide(targetIndex);
        startAutoSlide(); // Restart auto-sliding after manual navigation
      });
    });
    
    // Pause auto-sliding when hovering over the slider
    testimonialsSlider.addEventListener('mouseenter', stopAutoSlide);
    testimonialsSlider.addEventListener('mouseleave', startAutoSlide);
    
    // Handle touch events for mobile
    let touchStartX = 0;
    let touchEndX = 0;
    
    testimonialsSlider.addEventListener('touchstart', (e) => {
      touchStartX = e.changedTouches[0].screenX;
      stopAutoSlide();
    }, { passive: true });
    
    testimonialsSlider.addEventListener('touchend', (e) => {
      touchEndX = e.changedTouches[0].screenX;
      handleSwipe();
      startAutoSlide();
    }, { passive: true });
    
    function handleSwipe() {
      const swipeThreshold = 50; // Minimum distance to register as a swipe
      const swipeDistance = touchEndX - touchStartX;
      
      if (swipeDistance > swipeThreshold) {
        // Swiped right, go to previous slide
        goToSlide(currentIndex - 1);
      } else if (swipeDistance < -swipeThreshold) {
        // Swiped left, go to next slide
        goToSlide(currentIndex + 1);
      }
    }
    
    // Initialize the slider
    initializeSlider();
    
    // Handle window resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(() => {
        // Recalculate visible cards and reposition slider on resize
        const oldVisibleCards = visibleCards;
        calculateVisibleCards();
        
        // If the number of visible cards has changed, reinitialize the slider
        if (oldVisibleCards !== visibleCards) {
          initializeSlider();
        } else {
          // Otherwise just reposition
          goToSlide(currentIndex);
        }
      }, 200);
    });
  })();
  
  } catch (error) {
    console.error('An error occurred in the main script:', error);
    // Prevent infinite reload loops
    if (sessionStorage.getItem('reloadAttempt')) {
      sessionStorage.removeItem('reloadAttempt');
      console.error('Reload attempt failed, preventing further automatic reloads');
    } else {
      sessionStorage.setItem('reloadAttempt', 'true');
      // Only reload once to prevent infinite loops
      console.log('Attempting to reload page once...');
      // window.location.reload();
    }
  }
});
