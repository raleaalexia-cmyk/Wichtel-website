/* creates one snowflake */
function createSnowflake() {
    let snowflake = document.createElement('div'); /* makes a new div */
    snowflake.className = 'snow'; /* gives it the snow class */
    snowflake.textContent = '❄'; /* puts the snowflake character */

    snowflake.style.left = Math.random() * 100 + 'vw'; /* random left position */
    snowflake.style.animationDuration = (Math.random() * 4 + 3) + 's'; /* random speed */
    snowflake.style.fontSize = (Math.random() * 15 + 10) + 'px'; /* random size */

    document.body.appendChild(snowflake); /* adds it to the page */

    setTimeout(() => {
        snowflake.remove(); /* removes it after some seconds */
    }, 7000);
}

/* starts making snowflakes every 300ms */
setInterval(createSnowflake, 300);
