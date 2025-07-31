document.addEventListener('DOMContentLoaded', function() {
    // URL de tu API PHP
    const API_URL = 'api.php'; // Asegúrate de que esta ruta sea correcta

    // Configuración inicial
    const config = {
        workingHours: {
            start: 8, // 8am
            end: 17, // 5pm (hasta las 4pm para que la última reserva sea de 4-5pm)
        },
        // Miércoles (3) a Sábado (6). Domingo (0), Lunes (1), Martes (2) están cerrados.
        workingDays: [3, 4, 5, 6], 
        slotDuration: 60, // 60 minutos por reserva
        closedMessage: "Cerrado",
        timeFormat: '12h' // '12h' o '24h'
    };

    // Estado de la aplicación
    let state = {
        currentDate: new Date(),
        reservations: [], // Las reservas se cargarán desde la DB
        selectedSlot: null
    };

    // Elementos del DOM
    const elements = {
        daysContainer: document.getElementById('days-container'),
        monthYear: document.getElementById('current-month-year'),
        today: document.getElementById('today'),
        nextWeek: document.getElementById('next-week'),
        reservationSummary: document.getElementById('reservation-summary'),
        reservationModal: document.getElementById('reservationModal'),
        closeButton: document.querySelector('.close-button'),
        modalSelectedTime: document.getElementById('modalSelectedTime'),
        reservationForm: document.getElementById('reservationForm')
    };

    // Manejo de eventos
    elements.nextWeek.addEventListener('click', () => {
        state.currentDate.setDate(state.currentDate.getDate() + 7);
        renderCalendar();
    });

    elements.today.addEventListener('click', () => {
        state.currentDate = new Date();
        renderCalendar();
    });

    elements.closeButton.addEventListener('click', () => {
        elements.reservationModal.style.display = 'none';
        cancelSelection(); // Cancela la selección si se cierra el modal
    });

    window.addEventListener('click', (event) => {
        if (event.target == elements.reservationModal) {
            elements.reservationModal.style.display = 'none';
            cancelSelection(); // Cancela la selección si se hace clic fuera del modal
        }
    });

    elements.reservationForm.addEventListener('submit', function(event) {
        event.preventDefault(); // Evitar el envío del formulario por defecto
        confirmReservation();
    });

    // Inicializar el calendario
    renderCalendar();

    async function fetchReservations() {
        try {
            const response = await fetch(API_URL + '?action=get_reserved_slots');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const reservedSlots = await response.json();
            state.reservations = reservedSlots; // Actualiza el estado con las reservas de la DB
        } catch (error) {
            console.error('Error al cargar reservas:', error);
            alert('Error al cargar las reservas. Por favor, inténtalo de nuevo más tarde.');
        }
    }

    async function renderCalendar() {
        await fetchReservations(); // Cargar reservas antes de renderizar
        elements.daysContainer.innerHTML = '';
        updateMonthYearDisplay();

        const weekStart = getWeekStartDate(state.currentDate);
        
        for (let i = 0; i < 7; i++) {
            const currentDate = new Date(weekStart);
            currentDate.setDate(weekStart.getDate() + i);

            if (config.workingDays.includes(currentDate.getDay())) {
                renderDay(currentDate);
            }
        }
    }

    function renderDay(date) {
        const dayElement = document.createElement('div');
        dayElement.className = 'day';

        const dayHeader = document.createElement('div');
        dayHeader.className = 'day-header';
        dayHeader.textContent = formatDayHeader(date);
        dayElement.appendChild(dayHeader);

        if (!config.workingDays.includes(date.getDay())) {
            const closedMessage = document.createElement('div');
            closedMessage.className = 'closed-day';
            closedMessage.textContent = config.closedMessage;
            dayElement.appendChild(closedMessage);
        } else {
            const timeSlots = document.createElement('div');
            timeSlots.className = 'time-slots';

            for (let hour = config.workingHours.start; hour < config.workingHours.end; hour++) {
                const slotTime = new Date(date);
                slotTime.setHours(hour, 0, 0, 0);

                const slotKey = getSlotKey(slotTime);
                // Verificar si la franja está en las reservas cargadas de la DB
                const isReserved = state.reservations.includes(slotKey);
                const isSelected = state.selectedSlot && state.selectedSlot.slot === slotKey;

                const slotElement = document.createElement('div');
                slotElement.className = `time-slot ${isReserved ? 'reserved' : 'available'} ${isSelected ? 'selected' : ''}`;
                slotElement.textContent = formatTime(hour);
                
                if (!isReserved) {
                    slotElement.addEventListener('click', () => handleSlotClick(slotTime, slotKey));
                }

                timeSlots.appendChild(slotElement);
            }

            dayElement.appendChild(timeSlots);
        }

        elements.daysContainer.appendChild(dayElement);
    }

    function handleSlotClick(slotTime, slotKey) {
        state.selectedSlot = {
            date: slotTime,
            slot: slotKey,
            formattedTime: formatDayHeader(slotTime) + ' ' + formatTime(slotTime.getHours())
        };
        
        renderCalendar(); // Volver a renderizar para actualizar la selección visual
        updateReservationSummary();
    }

    function updateReservationSummary() {
        if (state.selectedSlot) {
            elements.reservationSummary.innerHTML = `
                <div><strong>Reserva seleccionada:</strong></div>
                <div>${state.selectedSlot.formattedTime}</div>
                <button id="open-modal-button" style="margin-top: 10px;">Continuar con la Reserva</button>
                <button id="cancel-selection" style="margin-top: 5px; background-color: #f44336;">Cancelar Selección</button>
            `;

            document.getElementById('open-modal-button').addEventListener('click', () => {
                elements.modalSelectedTime.textContent = state.selectedSlot.formattedTime;
                elements.reservationModal.style.display = 'flex'; // Mostrar el modal
            });
            document.getElementById('cancel-selection').addEventListener('click', cancelSelection);
        } else {
            elements.reservationSummary.innerHTML = '<div class="no-selection">No hay reserva seleccionada</div>';
        }
    }

    async function confirmReservation() {
        if (state.selectedSlot) {
            const formData = new FormData(elements.reservationForm);
            const userData = {
                slotKey: state.selectedSlot.slot,
                fechaReserva: state.selectedSlot.date.toISOString().split('T')[0], // YYYY-MM-DD
                horaReserva: formatTime24h(state.selectedSlot.date.getHours()), // HH:00:00
                nombre: formData.get('nombre'),
                apellido: formData.get('apellido'),
                rut: formData.get('rut'),
                correoElectronico: formData.get('correo'),
                numeroTelefono: formData.get('telefono')
            };

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(userData)
                });

                const result = await response.json();

                if (response.ok) {
                    alert(result.message);
                    elements.reservationModal.style.display = 'none'; // Ocultar modal
                    state.selectedSlot = null; // Limpiar la selección
                    elements.reservationForm.reset(); // Limpiar formulario
                    renderCalendar(); // Volver a renderizar para mostrar la franja como reservada
                    updateReservationSummary();
                } else {
                    alert(`Error: ${result.message || 'No se pudo completar la reserva.'}`);
                }
            } catch (error) {
                console.error('Error al enviar la reserva:', error);
                alert('Hubo un problema al intentar reservar. Por favor, inténtalo de nuevo.');
            }
        }
    }

    function cancelSelection() {
        state.selectedSlot = null;
        renderCalendar(); // Volver a renderizar para quitar la selección visual
        updateReservationSummary();
    }

    function updateMonthYearDisplay() {
        const options = { month: 'long', year: 'numeric' };
        elements.monthYear.textContent = state.currentDate.toLocaleDateString('es-ES', options);
    }

    // Funciones de utilidad
    function getWeekStartDate(date) {
        const result = new Date(date);
        result.setDate(date.getDate() - date.getDay());
        result.setHours(0, 0, 0, 0);
        return result;
    }

    function formatDayHeader(date) {
        const options = { weekday: 'long', day: 'numeric', month: 'short' };
        return date.toLocaleDateString('es-ES', options);
    }

    function formatTime(hour) {
        const hourNumber = typeof hour === 'number' ? hour : hour.getHours();
        
        if (config.timeFormat === '12h') {
            const ampm = hourNumber >= 12 ? 'PM' : 'AM';
            const displayHour = hourNumber % 12 || 12;
            return `${displayHour}:00 ${ampm}`;
        } else {
            return `${String(hourNumber).padStart(2, '0')}:00`;
        }
    }

    // Nueva función para obtener la hora en formato 24h para la DB
    function formatTime24h(hour) {
        const hourNumber = typeof hour === 'number' ? hour : hour.getHours();
        return `${String(hourNumber).padStart(2, '0')}:00:00`;
    }

    function getSlotKey(date) {
        return `${date.getFullYear()}-${date.getMonth() + 1}-${date.getDate()}-${date.getHours()}`;
    }
});
