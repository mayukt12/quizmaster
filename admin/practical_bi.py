import panda as pd
from sklearn.linear_model import LogisticRegression
data = pd.read_csv(r'C:/Users/bhard/OneDrive/Book1.csv')
x= data[['feature1','feature2']]
y= data[['total']]
model = Logisticregression(max_iter=1000)
model.fit(x,y)
print(model.predict([[75,690]]))