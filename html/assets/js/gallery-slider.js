/**
 * Shalimar Properties - Interactive Modern Gallery Slider & Modal Popup
 */
(() => {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('galleryLightbox');
    if (!modal) return;

    // Elements
    const dialog = document.getElementById('glDialog');
    const backdrop = document.getElementById('glBackdrop');
    const mainPageImg = document.getElementById('gMain');
    const pageThumbs = document.querySelectorAll('.gthumbs button');
    const openTriggers = document.querySelectorAll('[data-gallery-open]');
    const closeBtn = document.getElementById('glClose');
    const prevBtn = document.getElementById('glPrev');
    const nextBtn = document.getElementById('glNext');
    const mainSlide = document.getElementById('glMainImg');
    const currentCounter = document.getElementById('glCurrent');
    const totalCounter = document.getElementById('glTotal');
    const thumbRail = document.getElementById('glThumbRail');
    const playBtn = document.getElementById('glPlay');
    const zoomInBtn = document.getElementById('glZoomIn');
    const zoomOutBtn = document.getElementById('glZoomOut');
    const fsBtn = document.getElementById('glFullscreen');
    const progressBar = document.getElementById('glProgress');

    // Extract image list from JSON script or DOM
    const imagesDataElem = document.getElementById('galleryImagesData');
    let images = [];
    if (imagesDataElem) {
      try {
        images = JSON.parse(imagesDataElem.textContent);
      } catch (e) {
        images = [];
      }
    }

    if (!images || !images.length) {
      pageThumbs.forEach(btn => {
        if (btn.dataset.src) images.push(btn.dataset.src);
      });
      if (!images.length && mainPageImg) {
        images.push(mainPageImg.src);
      }
    }

    let currentIndex = 0;
    let isAutoplay = true; // Default: Run autoplay automatically
    let autoplayTimer = null;
    let isZoomed = false;
    let isClosing = false;
    let closeTimeout = null;
    const AUTOPLAY_DELAY = 3800;

    // Render modal thumbnails
    if (thumbRail && images.length > 1) {
      thumbRail.innerHTML = '';
      images.forEach((imgSrc, idx) => {
        const thumbBtn = document.createElement('button');
        thumbBtn.type = 'button';
        thumbBtn.className = 'gl-thumb-item' + (idx === 0 ? ' is-active' : '');
        thumbBtn.setAttribute('aria-label', `Thumbnail ${idx + 1}`);
        thumbBtn.dataset.index = idx;
        const img = document.createElement('img');
        img.src = imgSrc;
        img.alt = `Photo ${idx + 1}`;
        img.loading = 'lazy';
        thumbBtn.appendChild(img);
        thumbBtn.addEventListener('click', () => {
          goToSlide(idx);
          if (isAutoplay) restartAutoplayProgress();
        });
        thumbRail.appendChild(thumbBtn);
      });
    }

    if (totalCounter) {
      totalCounter.textContent = images.length;
    }

    // Preload helper
    const preload = (url) => {
      if (!url) return;
      const img = new Image();
      img.src = url;
    };

    // Update Display
    const updateSlide = (direction = 0) => {
      if (!images[currentIndex]) return;

      resetZoom();

      if (direction !== 0 && mainSlide) {
        const outClass = direction > 0 ? 'is-sliding-out-left' : 'is-sliding-out-right';
        mainSlide.classList.add(outClass);

        setTimeout(() => {
          mainSlide.src = images[currentIndex];
          mainSlide.classList.remove(outClass);
          mainSlide.classList.add('is-sliding-in');
          setTimeout(() => mainSlide.classList.remove('is-sliding-in'), 200);
        }, 130);
      } else if (mainSlide) {
        mainSlide.src = images[currentIndex];
      }

      if (currentCounter) {
        currentCounter.textContent = currentIndex + 1;
      }

      // Update Lightbox thumbnails
      if (thumbRail) {
        const thumbs = thumbRail.querySelectorAll('.gl-thumb-item');
        thumbs.forEach((t, i) => {
          t.classList.toggle('is-active', i === currentIndex);
          if (i === currentIndex) {
            t.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
          }
        });
      }

      // Update On-Page Main Image & Thumbs too
      if (mainPageImg && images[currentIndex]) {
        mainPageImg.src = images[currentIndex];
      }
      if (pageThumbs.length) {
        pageThumbs.forEach((pb, pi) => {
          pb.classList.toggle('on', pi === currentIndex);
        });
      }

      // Preload next & previous
      const nextIdx = (currentIndex + 1) % images.length;
      const prevIdx = (currentIndex - 1 + images.length) % images.length;
      preload(images[nextIdx]);
      preload(images[prevIdx]);
    };

    const goToSlide = (newIndex, direction = 0) => {
      if (newIndex < 0) {
        newIndex = images.length - 1;
      } else if (newIndex >= images.length) {
        newIndex = 0;
      }
      if (newIndex === currentIndex && direction === 0) return;
      if (direction === 0) {
        direction = newIndex > currentIndex ? 1 : -1;
      }
      currentIndex = newIndex;
      updateSlide(direction);
    };

    const nextSlide = () => goToSlide(currentIndex + 1, 1);
    const prevSlide = () => goToSlide(currentIndex - 1, -1);

    // Zoom Controls
    const resetZoom = () => {
      isZoomed = false;
      if (mainSlide) {
        mainSlide.classList.remove('is-zoomed');
        mainSlide.style.transform = '';
      }
    };

    const toggleZoom = () => {
      isZoomed = !isZoomed;
      if (mainSlide) {
        mainSlide.classList.toggle('is-zoomed', isZoomed);
      }
    };

    if (mainSlide) {
      mainSlide.addEventListener('click', toggleZoom);
    }
    if (zoomInBtn) {
      zoomInBtn.addEventListener('click', () => {
        isZoomed = true;
        if (mainSlide) mainSlide.classList.add('is-zoomed');
      });
    }
    if (zoomOutBtn) {
      zoomOutBtn.addEventListener('click', resetZoom);
    }

    // Autoplay Slideshow
    const animateProgressBar = () => {
      if (!progressBar) return;
      progressBar.style.transition = 'none';
      progressBar.style.width = '0%';
      setTimeout(() => {
        if (!isAutoplay) return;
        progressBar.style.transition = `width ${AUTOPLAY_DELAY}ms linear`;
        progressBar.style.width = '100%';
      }, 40);
    };

    const startAutoplay = () => {
      if (images.length <= 1) return;
      if (autoplayTimer) {
        clearInterval(autoplayTimer);
        autoplayTimer = null;
      }
      isAutoplay = true;
      if (playBtn) {
        playBtn.classList.add('gl-btn-active');
        playBtn.innerHTML = '<i class="fa-solid fa-pause"></i>';
        playBtn.title = 'Pause Slideshow (Space)';
      }
      animateProgressBar();
      autoplayTimer = setInterval(() => {
        nextSlide();
        animateProgressBar();
      }, AUTOPLAY_DELAY);
    };

    const stopAutoplay = () => {
      isAutoplay = false;
      if (autoplayTimer) {
        clearInterval(autoplayTimer);
        autoplayTimer = null;
      }
      if (playBtn) {
        playBtn.classList.remove('gl-btn-active');
        playBtn.innerHTML = '<i class="fa-solid fa-play"></i>';
        playBtn.title = 'Play Slideshow (Space)';
      }
      if (progressBar) {
        progressBar.style.transition = 'none';
        progressBar.style.width = '0%';
      }
    };

    const restartAutoplayProgress = () => {
      if (!isAutoplay) return;
      if (autoplayTimer) {
        clearInterval(autoplayTimer);
        autoplayTimer = null;
      }
      animateProgressBar();
      autoplayTimer = setInterval(() => {
        nextSlide();
        animateProgressBar();
      }, AUTOPLAY_DELAY);
    };

    if (playBtn) {
      playBtn.addEventListener('click', () => {
        if (isAutoplay) {
          stopAutoplay();
        } else {
          startAutoplay();
        }
      });
    }

    // Fullscreen / Windowed Modal Mode Toggle
    const toggleFullscreen = () => {
      if (!dialog) return;
      const isFs = dialog.classList.toggle('is-fullscreen');
      if (isFs) {
        fsBtn.classList.add('gl-btn-active');
        fsBtn.innerHTML = '<i class="fa-solid fa-compress"></i> <span class="gl-btn-label">Exit Full Screen</span>';
        fsBtn.title = 'Exit Full Screen (F)';
        if (!document.fullscreenElement && modal.requestFullscreen) {
          modal.requestFullscreen().catch(() => {});
        }
      } else {
        fsBtn.classList.remove('gl-btn-active');
        fsBtn.innerHTML = '<i class="fa-solid fa-expand"></i> <span class="gl-btn-label">Full Screen</span>';
        fsBtn.title = 'Full Screen (F)';
        if (document.fullscreenElement && document.exitFullscreen) {
          document.exitFullscreen().catch(() => {});
        }
      }
    };

    if (fsBtn) {
      fsBtn.addEventListener('click', toggleFullscreen);
      document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && dialog && dialog.classList.contains('is-fullscreen')) {
          dialog.classList.remove('is-fullscreen');
          fsBtn.classList.remove('gl-btn-active');
          fsBtn.innerHTML = '<i class="fa-solid fa-expand"></i> <span class="gl-btn-label">Full Screen</span>';
          fsBtn.title = 'Full Screen (F)';
        }
      });
    }

    // Open & Close with Smooth Entrance and Exit Animations
    const openLightbox = (index = 0) => {
      if (closeTimeout) {
        clearTimeout(closeTimeout);
        closeTimeout = null;
      }
      isClosing = false;
      currentIndex = index;

      if (dialog) dialog.classList.remove('is-fullscreen');
      if (fsBtn) {
        fsBtn.classList.remove('gl-btn-active');
        fsBtn.innerHTML = '<i class="fa-solid fa-expand"></i> <span class="gl-btn-label">Full Screen</span>';
        fsBtn.title = 'Full Screen (F)';
      }

      modal.classList.remove('is-closing');
      modal.classList.add('is-open');
      document.body.style.overflow = 'hidden';
      updateSlide(0);

      // Start running by default on open
      startAutoplay();
    };

    const closeLightbox = () => {
      if (isClosing || !modal.classList.contains('is-open')) return;
      isClosing = true;

      stopAutoplay();
      resetZoom();

      // Trigger smooth closing transition
      modal.classList.add('is-closing');
      modal.classList.remove('is-open');
      document.body.style.overflow = '';

      if (document.fullscreenElement && document.exitFullscreen) {
        document.exitFullscreen().catch(() => {});
      }

      closeTimeout = setTimeout(() => {
        modal.classList.remove('is-closing');
        if (dialog) dialog.classList.remove('is-fullscreen');
        isClosing = false;
        closeTimeout = null;
      }, 280);
    };

    // Attach open triggers on page
    openTriggers.forEach(el => {
      el.addEventListener('click', (e) => {
        e.preventDefault();
        openLightbox(currentIndex);
      });
    });

    if (mainPageImg) {
      mainPageImg.addEventListener('click', () => {
        openLightbox(currentIndex);
      });
    }

    pageThumbs.forEach((btn, idx) => {
      btn.addEventListener('click', (e) => {
        currentIndex = idx;
        if (mainPageImg && btn.dataset.src) {
          mainPageImg.src = btn.dataset.src;
        }
        pageThumbs.forEach((x, i) => x.classList.toggle('on', i === idx));
      });
      // Double click opens slider directly
      btn.addEventListener('dblclick', () => {
        openLightbox(idx);
      });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
    if (backdrop) backdrop.addEventListener('click', closeLightbox);

    if (prevBtn) {
      prevBtn.addEventListener('click', () => {
        prevSlide();
        if (isAutoplay) restartAutoplayProgress();
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', () => {
        nextSlide();
        if (isAutoplay) restartAutoplayProgress();
      });
    }

    // Keyboard Shortcuts
    document.addEventListener('keydown', (e) => {
      if (!modal.classList.contains('is-open')) return;

      if (e.key === 'Escape') {
        closeLightbox();
      } else if (e.key === 'ArrowRight' || e.key === 'd' || e.key === 'D') {
        nextSlide();
        if (isAutoplay) restartAutoplayProgress();
      } else if (e.key === 'ArrowLeft' || e.key === 'a' || e.key === 'A') {
        prevSlide();
        if (isAutoplay) restartAutoplayProgress();
      } else if (e.key === 'f' || e.key === 'F') {
        toggleFullscreen();
      } else if (e.key === ' ') {
        e.preventDefault();
        playBtn?.click();
      }
    });

    // Touch Swipe Support
    let touchStartX = 0;
    let touchStartY = 0;
    let touchEndX = 0;
    let touchEndY = 0;

    const stage = document.querySelector('.gl-stage');
    if (stage) {
      stage.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
        touchStartY = e.changedTouches[0].screenY;
      }, { passive: true });

      stage.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        touchEndY = e.changedTouches[0].screenY;
        handleSwipe();
      }, { passive: true });
    }

    const handleSwipe = () => {
      const deltaX = touchEndX - touchStartX;
      const deltaY = touchEndY - touchStartY;
      if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 45) {
        if (deltaX < 0) {
          nextSlide(); // Swiped left -> next
        } else {
          prevSlide(); // Swiped right -> prev
        }
        if (isAutoplay) restartAutoplayProgress();
      }
    };
  });
})();
