$(function () {
    var availableFoods = [
        { label: "Spicy Tomato Pasta", value: "Spicy Tomato Pasta", image: "image/img1.jpg", price: "$14.50" },
        { label: "Cheeseburger Deluxe", value: "Cheeseburger Deluxe", image: "image/img2.jpg", price: "$14.50" },
        { label: "Grilled Steak", value: "Grilled Steak", image: "image/steak.jpg", price: "$14.50" },
        { label: "Sushi Platter", value: "Sushi Platter", image: "image/sushi.jpg", price: "$14.50" },
        { label: "Chocolate Cake", value: "Chocolate Cake", image: "image/chocolatecake.jpg", price: "$14.50" },
        { label: "Ice Cream Sundae", value: "Ice Cream Sundae", image: "image/icecream.jpg", price: "$14.50" },
        { label: "Fresh Lemonade", value: "Fresh Lemonade", image: "image/lemonade.jpg", price: "$14.50" },
        { label: "Iced Coffee", value: "Iced Coffee", image: "image/coffee.jpg", price: "$14.50" },
        { label: "Vanilla Cupcake", value: "Vanilla Cupcake", image: "image/cupcake.jpg", price: "$14.50" },
        { label: "Cheesecake", value: "Cheesecake", image: "image/cheesecake.jpg", price: "$14.50" },
        { label: "Strawberries", value: "Strawberries", image: "image/strawberries.jpg", price: "$14.50" },
        { label: "Watermelon", value: "Watermelon", image: "image/watermelon.jpg", price: "$14.50" },
        { label: "Fries", value: "Fries", image: "image/fries.jpg", price: "$14.50" },
        { label: "Salad", value: "Salad", image: "image/salad.jpg", price: "$14.50" }
    ];

    $("#searchfoods").autocomplete({
        source: availableFoods,
        select: function (event, ui) {
            var selectedFood = ui.item.value;
            if (selectedFood === "Spicy Tomato Pasta") {
                window.location.href = "pasta.html";
            } else if (selectedFood === "Cheeseburger Deluxe") {
                window.location.href = "cheeseburger.html";
            }
            else {
                alert("Bạn đã chọn: " + selectedFood);
            }
        }
    }).autocomplete("instance")._renderItem = function (ul, item) {
        return $("<li>")
            .append("<div style='background:none; border:none; color: black;font-weight: bold; display: flex; align-items: center;'>" +
                "<img src='" + item.image + "'style='width:50px; height:50px; margin-right:10px;'/>" +
                "<div style='display: flex; flex-direction: column;'>" +
                "<div>" + item.label + "</div>" +
                "<div style='font-weight: normal; font-style: italic; font-size: 1em;'>" + item.price + "</div>" +
                "</div>" +
                "</div>")
            .appendTo(ul);
    };
});