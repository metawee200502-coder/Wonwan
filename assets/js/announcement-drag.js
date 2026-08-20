document.addEventListener('DOMContentLoaded', () => {
    const style = document.createElement('style');
    style.textContent = `
        .announcement-strip{position:relative}.announcement-strip .announcement-list{scrollbar-width:none!important;-ms-overflow-style:none!important;overflow-y:hidden!important;scroll-behavior:smooth;scroll-snap-type:x mandatory;cursor:default;padding-left:2px;padding-right:2px}.announcement-strip .announcement-list::-webkit-scrollbar{display:none!important;width:0!important;height:0!important}.announcement-strip .announcement-card{flex:0 0 min(680px,76vw)!important;scroll-snap-align:center!important}.announcement-carousel-button{position:absolute;top:50%;z-index:5;width:42px;height:42px;display:grid;place-items:center;border:1px solid #ead2dc;border-radius:50%;background:rgba(255,255,255,.96);color:#a94f71;font:600 26px/1 sans-serif;box-shadow:0 5px 16px rgba(98,55,72,.15);cursor:pointer;transform:translateY(-15%);transition:transform .2s ease,background .2s ease,opacity .2s ease}.announcement-carousel-button:hover{background:#f9e4eb;transform:translateY(-15%) scale(1.06)}.announcement-carousel-button.prev{left:8px}.announcement-carousel-button.next{right:8px}.announcement-dots{display:flex;justify-content:center;align-items:center;gap:7px;margin-top:7px}.announcement-dot-button{width:8px;height:8px;padding:0;border:0;border-radius:99px;background:#dfc3cd;cursor:pointer;transition:width .25s ease,background .25s ease,transform .25s ease}.announcement-dot-button.active{width:24px;background:#c66f90}.announcement-dot-button:hover{transform:scale(1.15)}@media(max-width:600px){.announcement-strip .announcement-card{flex-basis:84vw!important}.announcement-carousel-button{width:36px;height:36px;font-size:22px}.announcement-carousel-button.prev{left:2px}.announcement-carousel-button.next{right:2px}}@media(prefers-reduced-motion:reduce){.announcement-strip .announcement-list{scroll-behavior:auto}}
    `;
    document.head.appendChild(style);

    document.querySelectorAll('.announcement-list').forEach((rail) => {
        const originals = [...rail.querySelectorAll('.announcement-card')];
        if (!originals.length) return;
        const strip = rail.closest('.announcement-strip');
        const count = originals.length;
        let activeIndex = 0;
        let autoTimer = null;
        let resetTimer = null;
        let programmatic = false;

        const cardWidth = originals[0].getBoundingClientRect().width || 1;
        const cloneCount = count > 1 ? Math.min(count, Math.ceil(rail.clientWidth / cardWidth) + 1) : 0;
        if (count > 1) {
            const beforeFragment = document.createDocumentFragment();
            originals.slice(-cloneCount).forEach((card) => {
                const clone = card.cloneNode(true); clone.classList.add('is-carousel-clone'); clone.setAttribute('aria-hidden','true'); beforeFragment.append(clone);
            });
            const afterFragment = document.createDocumentFragment();
            originals.slice(0,cloneCount).forEach((card) => {
                const clone = card.cloneNode(true); clone.classList.add('is-carousel-clone'); clone.setAttribute('aria-hidden','true'); afterFragment.append(clone);
            });
            rail.prepend(beforeFragment); rail.append(afterFragment);
        }
        const slides = [...rail.querySelectorAll('.announcement-card')];
        const slideTarget = (slideIndex) => {
            const slide = slides[slideIndex];
            return slide.offsetLeft + (slide.offsetWidth / 2) - (rail.clientWidth / 2);
        };

        const previous = document.createElement('button');
        previous.type = 'button'; previous.className = 'announcement-carousel-button prev';
        previous.setAttribute('aria-label', 'ประกาศก่อนหน้า'); previous.innerHTML = '&#8249;';
        const next = document.createElement('button');
        next.type = 'button'; next.className = 'announcement-carousel-button next';
        next.setAttribute('aria-label', 'ประกาศถัดไป'); next.innerHTML = '&#8250;';
        const dots = document.createElement('div');
        dots.className = 'announcement-dots'; dots.setAttribute('aria-label', 'เลือกประกาศ');

        const dotButtons = originals.map((_, index) => {
            const dot = document.createElement('button');
            dot.type = 'button'; dot.className = 'announcement-dot-button';
            dot.setAttribute('aria-label', `ไปประกาศที่ ${index + 1}`);
            dot.addEventListener('click', () => goTo(index, true));
            dots.append(dot); return dot;
        });

        const updateControls = () => {
            dotButtons.forEach((dot, index) => dot.classList.toggle('active', index === activeIndex));
            previous.disabled = next.disabled = count < 2;
            previous.style.opacity = next.style.opacity = count < 2 ? '.35' : '1';
        };

        const jumpWithoutAnimation = (slideIndex) => {
            const oldBehavior = rail.style.scrollBehavior;
            rail.style.scrollBehavior = 'auto';
            rail.scrollLeft = slideTarget(slideIndex);
            rail.getBoundingClientRect();
            rail.style.scrollBehavior = oldBehavior;
        };

        function goTo(index, userInitiated = false) {
            if (count < 2) return;
            if (resetTimer) clearTimeout(resetTimer);
            programmatic = true;
            const movingPastEnd = index >= count;
            const movingPastStart = index < 0;
            activeIndex = movingPastEnd ? 0 : (movingPastStart ? count - 1 : index);
            const targetSlide = movingPastEnd ? cloneCount + count : (movingPastStart ? cloneCount - 1 : cloneCount + activeIndex);
            rail.scrollTo({left:slideTarget(targetSlide),behavior:'smooth'});
            updateControls();
            resetTimer = setTimeout(() => {
                if (movingPastEnd) jumpWithoutAnimation(cloneCount);
                else if (movingPastStart) jumpWithoutAnimation(cloneCount + count - 1);
                programmatic = false;
            }, 650);
            if (userInitiated) startAutoPlay();
        }

        const stopAutoPlay = () => { if(autoTimer)clearInterval(autoTimer);autoTimer=null; };
        function startAutoPlay(){stopAutoPlay();if(count>1&&!matchMedia('(prefers-reduced-motion: reduce)').matches)autoTimer=setInterval(()=>goTo(activeIndex+1),4500);}
        previous.addEventListener('click',()=>goTo(activeIndex-1,true));
        next.addEventListener('click',()=>goTo(activeIndex+1,true));

        let scrollDebounce=null;
        rail.addEventListener('scroll',()=>{
            if(programmatic)return;
            clearTimeout(scrollDebounce);
            scrollDebounce=setTimeout(()=>{
                let nearest=cloneCount,distance=Infinity;
                slides.forEach((slide,index)=>{const d=Math.abs(slideTarget(index)-rail.scrollLeft);if(d<distance){distance=d;nearest=index;}});
                if(nearest<cloneCount){activeIndex=(nearest-cloneCount+count)%count;jumpWithoutAnimation(cloneCount+activeIndex);}
                else if(nearest>=cloneCount+count){activeIndex=(nearest-cloneCount)%count;jumpWithoutAnimation(cloneCount+activeIndex);}
                else activeIndex=nearest-cloneCount;
                updateControls();
            },100);
        },{passive:true});

        strip.addEventListener('mouseenter',stopAutoPlay);
        strip.addEventListener('mouseleave',startAutoPlay);
        strip.addEventListener('focusin',stopAutoPlay);
        strip.addEventListener('focusout',startAutoPlay);
        document.addEventListener('visibilitychange',()=>document.hidden?stopAutoPlay():startAutoPlay());
        let resizeTimer=null;
        window.addEventListener('resize',()=>{
            clearTimeout(resizeTimer);
            resizeTimer=setTimeout(()=>jumpWithoutAnimation(cloneCount+activeIndex),120);
        });
        strip.append(previous,next,dots);
        requestAnimationFrame(()=>{if(count>1)jumpWithoutAnimation(cloneCount);updateControls();startAutoPlay();});
    });
});
