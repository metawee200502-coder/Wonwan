
 let currentDeleteForm = null;
 function handleThreadDelete(e, form) {
     e.preventDefault();
     currentDeleteForm = form;
     document.getElementById('deleteConfirmModal').style.display = 'flex';
     return false;
 }
 function closeDeleteModal() {
     document.getElementById('deleteConfirmModal').style.display = 'none';
     currentDeleteForm = null;
 }
 document.addEventListener('DOMContentLoaded', () => {
     window.addEventListener('message', function(e) {
         if(e.data && e.data.action === 'close_condition_modal') {
             const m = document.getElementById('conditionModal');
             if(m) {
                 m.style.display = 'none';
                 document.getElementById('conditionIframe').src = '';
                 window.location.reload();
             }
         }
     });
     
     document.getElementById('confirmDeleteBtn')?.addEventListener('click', () => {
         if(currentDeleteForm) currentDeleteForm.submit();
     });
     
     document.querySelectorAll('.thread-swipe-wrapper').forEach(wrapper => {
        let isDown = false;
        let startX;
        let scrollLeft;
        let isDragged = false;
        
        wrapper.querySelectorAll('a, img').forEach(el => el.draggable = false);
        
        wrapper.addEventListener('mousedown', (e) => {
            isDragged = false;
            if (e.target.closest('.thread-delete-btn')) return; // let buttons work
            isDown = true;
            startX = e.pageX - wrapper.offsetLeft;
            scrollLeft = wrapper.scrollLeft;
            wrapper.style.scrollSnapType = 'none';
        });
        wrapper.addEventListener('mouseleave', () => {
            if(!isDown) return;
            isDown = false;
            wrapper.style.scrollSnapType = 'x mandatory';
            if (wrapper.scrollLeft > 30) wrapper.scrollTo({left: 75, behavior: 'smooth'});
            else wrapper.scrollTo({left: 0, behavior: 'smooth'});
        });
        wrapper.addEventListener('mouseup', (e) => {
            if(!isDown) return;
            isDown = false;
            wrapper.style.scrollSnapType = 'x mandatory';
            if (wrapper.scrollLeft > 30) wrapper.scrollTo({left: 75, behavior: 'smooth'});
            else wrapper.scrollTo({left: 0, behavior: 'smooth'});
        });
        wrapper.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - wrapper.offsetLeft;
            const walk = (startX - x);
            if (Math.abs(walk) > 5) isDragged = true;
            wrapper.scrollLeft = scrollLeft + walk;
        });
        wrapper.addEventListener('click', (e) => {
            if (isDragged) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
});
