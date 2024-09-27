# Very basic LR model analysis, code from ChatGPT

# Import necessary libraries
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.linear_model import LinearRegression
from sklearn.metrics import mean_absolute_error, r2_score

# Load the dataset
data = pd.read_csv('hpmon_data_export.csv')

# Select the relevant features and target variable
features = ['Rating', 'Oversizing Factor', 'FlowT mean', 'OutsideT mean', '% Carnot', 'Heat output']
target = 'SPF'

# Split the data into features (X) and target (y)
X = data[features]
y = data[target]

# Split the data into training and testing sets (80% train, 20% test)
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

# Initialize the linear regression model
model = LinearRegression()

# Train the model using the training data
model.fit(X_train, y_train)

# Predict SPF for the test set
y_pred = model.predict(X_test)

# Evaluate the model's performance
mae = mean_absolute_error(y_test, y_pred)
r2 = r2_score(y_test, y_pred)

# Print the results
print(f"Mean Absolute Error (MAE): {mae}")
print(f"R² Score: {r2}")

# Provide predictions for each row in the dataset
data['Predicted SPF'] = model.predict(data[features])

# Save the predictions to a new CSV file
data.to_csv('spf_predictions.csv', index=False)
