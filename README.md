# cfbrisk_sim
CFB Risk simulator

These are scripts can do a basic simulation of a CFB Risk round, including a Monte Carlo type analysis to help a team predict what impact the assignment of star power to various territories might have on that team's expected wins.

The scripts are written in PHP, making them easy to drop into an Apache2/PHP webserver.  They do rely on the presence of the API endpoints at http://collegefootballrisk.com

risk_sim.php is the generalized tool requring only estimations of the total raw star power of all opponents and of the user's team.
assign.php is a slightly more specialized verision that asks for estimates of the average raw star power per territory expected from each team in the game for opponents, and a list of squad names, raw star power, and number of squad subdivisions for the user's team.

In both versions, the user first selects the day (roll #) from the drop down list and submits it, then selects the team they wish to play as.  The form will access the API to determine which territories that team can play in that turn.  The user then need to supply the opponent and friendly star powers, and use sliders to "weight" the opponent and friendly importance of each territory before submitting the form.  

In the case of the generalized tool, the script will consider 1 raw star at a time, and calculate its potential contribution to all potential territories, weighted per the user input, to find the territory which would provide the most benefit to the team to which the star belongs.  The opponent stars are assigned first, and then friendly stars are then assigned optimally, knowing how the opponent stars are arranged.

As a basic example, if there are two territories to which a star could be assigned evenly weighted, Territory A has 0 friendly and 4 opponent stars, and Territory B has 0 friendly and 5 opponent stars.
1 friendly star added to A would result in an odds increase of 0.2 ( +1 / (+1 + 4), while 1 star added to B would result in an odds increase of 0.167 ( +1 / (+1 + 5) ).  The algorithm would assign the star to Territory A since the odds incrase of 0.2 is preferable to 0.167.  

The specialized tool assigns opponent stars by adding the stars per territory for each potential opponent in each territory.  So if Opponent 1 can play 20 stars per territory, and Opponent 2 can play 15 stars, and both are neighboring Territory A, the opponent power in Territory A would be 35.
The friendly star power is assigned using the same "least cost" algorithm as before, but instead of considering each star individually, the specialized tool considers the star power of each subdivided squad.  So if Squad 1 with 120 stars is to be assigned 6 targets, 20 stars at a time would be considered by the tool.

The ultimate result of each simlation is a distrubution of star power that should maximize the Expected Result against the estiamted opponent distribution of power.  After each run, the user can tweak the values and weights provided to attempt to refine their model and compare different distributions.
