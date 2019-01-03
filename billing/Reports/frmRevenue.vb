Imports System.Data
Imports System.Data.SqlClient
Imports CrystalDecisions.CrystalReports.Engine
Imports CrystalDecisions.Shared
Public Class frmRevenue

    Private Sub frmRevenue_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        On Error Resume Next
        Dim a As Integer
        If repRevenue.XtraTabControl1.SelectedTabPageIndex = 0 Then
            a = repRevenue.cbPeriod.Text
        ElseIf repRevenue.XtraTabControl1.SelectedTabPageIndex = 1 Then
            a = repRevenue.txtvolume.Text
        End If
        TextBox1.Text = a
        myCon.open()
        Dim debitmisc, scipsi, tax, misc, steve, arrastre, docking, hustling, lift, shift, standby, stripping, lashing, forklift, scale, cranage, bagging As Decimal
        Dim psteve, parras, pdoc, phust, plift, pshift, pstand, pstrip, plash, pfork, pbagging As Decimal
        Dim stevedisc, arrasdisc As Decimal
        myCmd = New SqlCommand("SELECT ISNULL(sum(it_scipsi),0)  FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND it_scode in (59610,40001,40002,40005,40008,40009,40011,40012,40014,40015,40016,40017,40006,40004) AND bt_status = 'FALSE'", myCon)
        scipsi = myCmd.ExecuteNonQuery
        scipsi = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_tax),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND it_scode in (59610,40001,40002,40005,40008,40009,40011,40012,40014,40015,40016,40017,40006,40004) AND bt_status = 'FALSE'", myCon)
        tax = myCmd.ExecuteNonQuery
        tax = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40004", myCon)
        bagging = myCmd.ExecuteNonQuery
        bagging = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_ppa),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40004", myCon)
        pbagging = myCmd.ExecuteNonQuery
        pbagging = myCmd.ExecuteScalar


        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 59610", myCon)
        misc = myCmd.ExecuteNonQuery
        misc = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_ppa),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 59610", myCon)
        debitmisc = myCmd.ExecuteNonQuery
        debitmisc = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40001", myCon)
        steve = myCmd.ExecuteNonQuery
        steve = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40002", myCon)
        arrastre = myCmd.ExecuteNonQuery
        arrastre = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40005", myCon)
        docking = myCmd.ExecuteNonQuery
        docking = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40008", myCon)
        hustling = myCmd.ExecuteNonQuery
        hustling = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40009", myCon)
        lift = myCmd.ExecuteNonQuery
        lift = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0)  FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40011", myCon)
        shift = myCmd.ExecuteNonQuery
        shift = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0)  FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40012", myCon)
        standby = myCmd.ExecuteNonQuery
        standby = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40014", myCon)
        stripping = myCmd.ExecuteNonQuery
        stripping = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0)  FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40015", myCon)
        lashing = myCmd.ExecuteNonQuery
        lashing = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0)  FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40006", myCon)
        forklift = myCmd.ExecuteNonQuery
        forklift = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40016", myCon)
        scale = myCmd.ExecuteNonQuery
        scale = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_gross),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40017", myCon)
        cranage = myCmd.ExecuteNonQuery
        cranage = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_ppa),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40001", myCon)
        psteve = myCmd.ExecuteNonQuery
        psteve = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_ppa),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40002", myCon)
        parras = myCmd.ExecuteNonQuery
        parras = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_ppa),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40005", myCon)
        pdoc = myCmd.ExecuteNonQuery
        pdoc = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_ppa),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40008", myCon)
        phust = myCmd.ExecuteNonQuery
        phust = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_ppa),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40009", myCon)
        plift = myCmd.ExecuteNonQuery
        plift = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_ppa),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40011", myCon)
        pshift = myCmd.ExecuteNonQuery
        pshift = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_ppa),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40012", myCon)
        pstand = myCmd.ExecuteNonQuery
        pstand = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_ppa),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40014", myCon)
        pstrip = myCmd.ExecuteNonQuery
        pstrip = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_ppa),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40015", myCon)
        plash = myCmd.ExecuteNonQuery
        plash = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_ppa),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40006", myCon)
        pfork = myCmd.ExecuteNonQuery
        pfork = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_disc),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40001", myCon)
        stevedisc = myCmd.ExecuteNonQuery
        stevedisc = myCmd.ExecuteScalar

        myCmd = New SqlCommand("SELECT ISNULL(sum(it_disc),0) FROM tbl_bill_trans LEFT JOIN tbl_item_trans ON tbl_bill_trans.bt_bill_num=tbl_item_trans.it_bill_num WHERE bt_per = '" & TextBox1.Text & "' AND bt_status = 'FALSE' AND it_scode = 40002", myCon)
        arrasdisc = myCmd.ExecuteNonQuery
        arrasdisc = myCmd.ExecuteScalar


        Dim crystal As New revenue
        Dim objText As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtscipsi")
        objText.Text = FormatNumber(scipsi)

        Dim objText1 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtvat")
        objText1.Text = FormatNumber(tax)

        Dim objText2 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtmisc")
        objText2.Text = FormatNumber(misc)

        Dim objText3 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtsteve")
        objText3.Text = FormatNumber(steve)

        Dim objText4 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtarras")
        objText4.Text = FormatNumber(arrastre)

        Dim objText5 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtdoc")
        objText5.Text = FormatNumber(docking)

        Dim objText6 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txthust")
        objText6.Text = FormatNumber(hustling)

        Dim objText7 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtlift")
        objText7.Text = FormatNumber(lift)

        Dim objText8 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtshift")
        objText8.Text = FormatNumber(shift)

        Dim objText9 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtstandby")
        objText9.Text = FormatNumber(standby)

        Dim objText10 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtstrip")
        objText10.Text = FormatNumber(stripping)

        Dim objText11 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtlash")
        objText11.Text = FormatNumber(lashing)

        Dim objText12 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtscale")
        objText12.Text = FormatNumber(scale)

        Dim objText13 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtcranage")
        objText13.Text = FormatNumber(cranage)

        Dim objText14 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtdebitmisc")
        objText14.Text = FormatNumber(debitmisc)

        Dim objText01 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtforklift")
        objText01.Text = FormatNumber(forklift)

        Dim objText15 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtscipsitotal")
        objText15.Text = FormatNumber(steve + arrastre + docking + hustling + lift + shift + standby + stripping + lashing + scale + cranage + forklift + bagging)
        Dim totalscipsi As Decimal = steve + arrastre + docking + hustling + lift + shift + standby + stripping + lashing + scale + cranage + forklift + bagging

        Dim objText16 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtpsteve")
        objText16.Text = FormatNumber(psteve)

        Dim objText17 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtparrastre")
        objText17.Text = FormatNumber(parras)

        Dim objText18 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtpdoc")
        objText18.Text = FormatNumber(pdoc)

        Dim objText19 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtphust")
        objText19.Text = FormatNumber(phust)

        Dim objText20 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtplift")
        objText20.Text = FormatNumber(plift)

        Dim objText21 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtpshift")
        objText21.Text = FormatNumber(pshift)

        Dim objText22 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtpstand")
        objText22.Text = FormatNumber(pstand)

        Dim objText23 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtpstrip")
        objText23.Text = FormatNumber(pstrip)

        Dim objText24 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtplash")
        objText24.Text = FormatNumber(plash)

        Dim objText02 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtppafork")
        objText02.Text = FormatNumber(pfork)

        Dim objText25 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtppatotal")
        objText25.Text = FormatNumber(psteve + parras + pdoc + phust + plift + pshift + pstand + pstrip + plash + pfork + pbagging)
        Dim totalppa As Decimal = psteve + parras + pdoc + phust + plift + pshift + pstand + pstrip + plash + pfork + pbagging

        Dim objText26 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtstevdisc")
        objText26.Text = FormatNumber(stevedisc)

        Dim objText27 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtarrasdisc")
        objText27.Text = FormatNumber(arrasdisc)

        Dim objText28 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtdisctotal")
        objText28.Text = FormatNumber(stevedisc + arrasdisc)
        Dim totaldisc As Decimal = stevedisc + arrasdisc

        Dim objText29 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("totalgross")
        objText29.Text = objText15.Text

        Dim objText30 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("totalvat")
        objText30.Text = objText1.Text

        Dim objText31 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("totalmisccred")
        objText31.Text = objText2.Text

        Dim objText32 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("totalcredit")
        objText32.Text = FormatNumber(totalscipsi + tax + misc)

        Dim objText33 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("totalppa")
        objText33.Text = objText25.Text

        Dim objText34 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("totaldisc")
        objText34.Text = objText28.Text

        Dim objText35 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("totalscipsi")
        objText35.Text = objText.Text

        Dim objText36 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("totalmiscdeb")
        objText36.Text = objText14.Text

        Dim objText37 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("totaldebit")
        objText37.Text = FormatNumber(totalppa + totaldisc + scipsi + debitmisc)

        Dim objText38 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtperiod")
        objText38.Text = "PERIOD: " + TextBox1.Text

        Dim objText39 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtbagging")
        objText39.Text = FormatNumber(bagging)

        Dim objText40 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(3).ReportObjects("txtpbagging")
        objText40.Text = FormatNumber(pbagging)

        CrystalReportViewer1.ReportSource = crystal
        CrystalReportViewer1.Refresh()
        myCmd.Dispose()
        myCon.close()
    End Sub
    '        Try
    '        myCon.open()
    'Dim scipsi As String

    '        
    '    Catch ex As Exception
    '        MsgBox(ex.Message)
    '    Finally
    '        myCmd.Dispose()
    '        myCon.close()
    '    End Try

End Class