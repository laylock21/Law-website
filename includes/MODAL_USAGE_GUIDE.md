# Reusable Modal Container - Usage Guide

## Quick Start

### 1. Include Required Files

Add these three lines to any PHP page where you want to use modals:

```php
<!-- In the <head> section -->
<link rel="stylesheet" href="../includes/modal-container.css">

<!-- Before closing </body> tag -->
<?php include '../includes/modal-container.php'; ?>
<script src="../includes/modal-container.js"></script>
```

**Note:** Adjust the path (`../includes/`) based on your file location.

---

## Basic Usage Examples

### Example 1: Simple Modal

```javascript
ModalContainer.open({
    title: 'Welcome',
    body: '<p>This is a simple modal message.</p>'
});
```

### Example 2: Modal with Buttons

```javascript
ModalContainer.open({
    title: 'Confirm Action',
    body: '<p>Are you sure you want to proceed?</p>',
    footer: `
        <button onclick="ModalContainer.close()" class="btn btn-secondary">Cancel</button>
        <button onclick="handleConfirm()" class="btn btn-primary">Confirm</button>
    `
});

function handleConfirm() {
    // Your action here
    alert('Confirmed!');
    ModalContainer.close();
}
```

### Example 3: Form in Modal

```javascript
function openFormModal() {
    const formHTML = `
        <form id="myForm" onsubmit="handleSubmit(event)">
            <div class="form-group">
                <label>Name:</label>
                <input type="text" name="name" required class="form-input">
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required class="form-input">
            </div>
        </form>
    `;
    
    const footerHTML = `
        <button type="button" onclick="ModalContainer.close()" class="btn btn-secondary">Cancel</button>
        <button type="submit" form="myForm" class="btn btn-primary">Submit</button>
    `;
    
    ModalContainer.open({
        title: 'Contact Form',
        body: formHTML,
        footer: footerHTML,
        width: '500px'
    });
}

function handleSubmit(e) {
    e.preventDefault();
    // Process form data
    ModalContainer.close();
}
```

---

## Helper Methods

### Success Message
```javascript
ModalContainer.showSuccess('Your changes have been saved!');
```

### Error Message
```javascript
ModalContainer.showError('Something went wrong. Please try again.');
```

### Confirmation Dialog
```javascript
ModalContainer.confirm(
    'Are you sure you want to delete this item?',
    function() {
        // User clicked Confirm
        console.log('Item deleted');
    },
    'Delete Confirmation' // Optional title
);
```

### Loading Indicator
```javascript
ModalContainer.showLoading('Processing your request...');

// Later, close it
ModalContainer.close();
```

---

## API Reference

### ModalContainer.open(options)

Opens a modal with custom content.

**Options:**
- `title` (string): Modal title
- `body` (string|HTMLElement): Modal body content
- `footer` (string|HTMLElement): Modal footer content (optional)
- `width` (string): Custom width, e.g., '600px' (optional)
- `onClose` (function): Callback when modal closes (optional)

**Example:**
```javascript
ModalContainer.open({
    title: 'My Modal',
    body: '<p>Content here</p>',
    footer: '<button onclick="ModalContainer.close()">Close</button>',
    width: '700px',
    onClose: function() {
        console.log('Modal closed');
    }
});
```

### ModalContainer.close()

Closes the currently open modal.

```javascript
ModalContainer.close();
```

### ModalContainer.setTitle(title)

Updates the modal title while it's open.

```javascript
ModalContainer.setTitle('New Title');
```

### ModalContainer.setBody(body)

Updates the modal body content while it's open.

```javascript
ModalContainer.setBody('<p>New content</p>');
```

### ModalContainer.setFooter(footer)

Updates the modal footer while it's open.

```javascript
ModalContainer.setFooter('<button onclick="ModalContainer.close()">OK</button>');
```

### ModalContainer.showSuccess(message, title)

Shows a success message modal.

```javascript
ModalContainer.showSuccess('Operation completed!', 'Success'); // title is optional
```

### ModalContainer.showError(message, title)

Shows an error message modal.

```javascript
ModalContainer.showError('An error occurred.', 'Error'); // title is optional
```

### ModalContainer.confirm(message, onConfirm, title)

Shows a confirmation dialog.

```javascript
ModalContainer.confirm(
    'Delete this item?',
    function() {
        // User confirmed
    },
    'Confirm Delete' // title is optional
);
```

### ModalContainer.showLoading(message)

Shows a loading indicator.

```javascript
ModalContainer.showLoading('Please wait...');
```

---

## Complete Integration Example

Here's a complete example showing how to add the modal to a PHP page:

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Page</title>
    
    <!-- Your existing styles -->
    <link rel="stylesheet" href="styles.css">
    
    <!-- Add modal CSS -->
    <link rel="stylesheet" href="../includes/modal-container.css">
</head>
<body>
    <h1>My Page</h1>
    
    <!-- Your page content -->
    <button onclick="showMyModal()">Open Modal</button>
    
    <!-- Include modal HTML -->
    <?php include '../includes/modal-container.php'; ?>
    
    <!-- Your existing scripts -->
    <script src="script.js"></script>
    
    <!-- Add modal JavaScript -->
    <script src="../includes/modal-container.js"></script>
    
    <!-- Your custom modal functions -->
    <script>
        function showMyModal() {
            ModalContainer.open({
                title: 'Hello!',
                body: '<p>This is my custom modal.</p>',
                footer: '<button onclick="ModalContainer.close()">Close</button>'
            });
        }
    </script>
</body>
</html>
```

---

## Styling Tips

The modal uses these CSS classes that you can customize:

- `.modal-container` - Main container
- `.modal-overlay` - Dark background overlay
- `.modal-content` - Modal box
- `.modal-header` - Header section
- `.modal-title` - Title text
- `.modal-body` - Body content
- `.modal-footer` - Footer section
- `.modal-close` - Close button (×)

You can override these in your own CSS file if needed.

---

## Keyboard & Click Behavior

- **ESC key**: Closes the modal
- **Click overlay**: Closes the modal
- **Click × button**: Closes the modal

---

## Troubleshooting

### Modal doesn't appear
- Check that all three files are included (CSS, PHP, JS)
- Check the file paths are correct
- Open browser console for JavaScript errors

### Modal appears but looks broken
- Make sure `modal-container.css` is loaded
- Check for CSS conflicts with your existing styles

### Modal doesn't close
- Make sure you're calling `ModalContainer.close()`
- Check browser console for JavaScript errors

---

## File Structure

```
includes/
├── modal-container.php      # HTML structure (include in your pages)
├── modal-container.css      # Styles (link in <head>)
├── modal-container.js       # JavaScript API (include before </body>)
└── modal-container-example.html  # Full working examples
```
