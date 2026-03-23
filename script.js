// Original functionality preserved with minor optimizations


document.querySelectorAll(".folder-box").forEach(box => {


    box.addEventListener('click', function (e) {


        // Don't do anything if clicking on a link


        if (e.target.tagName === 'A') return;





        // Find all list items in THIS folder


        const fileItems = this.querySelectorAll("li");





        // Check if any files are currently visible


        const anyVisible = Array.from(fileItems).some(item =>


            item.classList.contains('li-folder-clicked'));





        // Toggle based on current state


        fileItems.forEach(item => {


            if (anyVisible) {


                // If any are visible, hide all


                item.classList.remove('li-folder-clicked');


                item.classList.add('li-folder-not-clicked');


            } else {


                // If all are hidden, show all


                item.classList.remove('li-folder-not-clicked');


                item.classList.add('li-folder-clicked');


            }


        });


    });


});





// Simple performance improvement: lazy load images if they exist


document.addEventListener('DOMContentLoaded', function() {


    const images = document.querySelectorAll('img[data-src]');


    if (images.length > 0 && 'IntersectionObserver' in window) {


        const imageObserver = new IntersectionObserver((entries, observer) => {


            entries.forEach(entry => {


                if (entry.isIntersecting) {


                    const img = entry.target;


                    img.src = img.dataset.src;


                    img.removeAttribute('data-src');


                    observer.unobserve(img);


                }


            });


        });


        images.forEach(img => imageObserver.observe(img));


    }


});